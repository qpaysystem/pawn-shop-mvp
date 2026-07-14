<?php

namespace App\Services\Meetings;

use App\Models\Employee;
use App\Services\CallRecordingTranscriptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/** Транскрипция собрания: ffmpeg → Whisper → разметка по участникам из журнала персонала. */
class MeetingTranscriptionService
{
    public function __construct(
        private CallRecordingTranscriptionService $callTranscription = new CallRecordingTranscriptionService,
        private MeetingLlmService $llm = new MeetingLlmService,
    ) {}

    /**
     * @return array{raw: string|null, formatted: string|null, error: string|null}
     */
    public function transcribeVideo(string $videoPath): array
    {
        $mp3Path = preg_replace('/\.[^.]+$/', '.mp3', $videoPath) ?? ($videoPath.'.mp3');
        if (! $this->extractAudio($videoPath, $mp3Path)) {
            return ['raw' => null, 'formatted' => null, 'error' => 'Не удалось извлечь аудио (нужен ffmpeg в контейнере).'];
        }

        $raw = $this->transcribeAudioFile($mp3Path);
        if ($raw === null || trim($raw) === '') {
            return ['raw' => null, 'formatted' => null, 'error' => 'Транскрипция не получена (проверьте OPENAI_API_KEY).'];
        }

        $formatted = $this->formatByPersonnel($raw);

        return [
            'raw' => $raw,
            'formatted' => $formatted ?? $raw,
            'error' => null,
        ];
    }

    public function extractAudio(string $videoPath, string $mp3Path): bool
    {
        $result = Process::timeout(900)->run([
            'ffmpeg', '-y', '-i', $videoPath,
            '-vn', '-ac', '1', '-ar', '16000', '-b:a', '48k',
            $mp3Path,
        ]);

        if (! $result->successful()) {
            Log::warning('meeting ffmpeg failed', [
                'exit' => $result->exitCode(),
                'error' => $result->errorOutput(),
            ]);

            return false;
        }

        return is_readable($mp3Path);
    }

    private function transcribeAudioFile(string $mp3Path): ?string
    {
        $sizeMb = filesize($mp3Path) / (1024 * 1024);
        if ($sizeMb <= 24) {
            return $this->callTranscription->transcribeWithWhisper($mp3Path);
        }

        $dir = dirname($mp3Path);
        $pattern = $dir.'/chunk_%02d.mp3';
        $chunkResult = Process::timeout(900)->run([
            'ffmpeg', '-y', '-i', $mp3Path,
            '-f', 'segment', '-segment_time', '120', '-c', 'copy',
            $pattern,
        ]);

        if (! $chunkResult->successful()) {
            return $this->callTranscription->transcribeWithWhisper($mp3Path);
        }

        $chunks = glob($dir.'/chunk_*.mp3') ?: [];
        sort($chunks);
        $parts = [];
        foreach ($chunks as $chunk) {
            $text = $this->callTranscription->transcribeWithWhisper($chunk);
            if ($text !== null && trim($text) !== '') {
                $parts[] = trim($text);
            }
            @unlink($chunk);
        }

        return $parts !== [] ? implode("\n\n", $parts) : null;
    }

    public function formatByPersonnel(string $rawTranscript): ?string
    {
        $roster = $this->buildPersonnelRoster();
        $system = <<<'PROMPT'
Ты секретарь утреннего собрания сети ломбардов. Отформатируй сырую стенограмму:
- расставь знаки препинания;
- разбей по репликам, каждая с новой строки;
- перед репликой укажи имя и должность участника в формате «Фамилия И.О. (должность):»;
- используй список сотрудников портала как подсказку, но не приписывай реплики наугад — если говорящий неясен, пиши «Участник:»;
- не добавляй комментариев и заголовков — только стенограмма.
PROMPT;

        $user = "Список сотрудников портала:\n".$roster."\n\nСырая стенограмма:\n\n".$rawTranscript;

        $result = $this->llm->chat($system, $user, 0.2, 8192);

        return $result['content'];
    }

    private function buildPersonnelRoster(): string
    {
        $employees = Employee::query()
            ->with(['store', 'user'])
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($employees->isEmpty()) {
            return '(журнал персонала пуст — используй нейтральные метки «Участник:»)';
        }

        $lines = [];
        foreach ($employees as $employee) {
            $role = $this->describeEmployee($employee);
            $lines[] = '- '.$employee->full_name.' — '.$role;
        }

        return implode("\n", $lines);
    }

    private function describeEmployee(Employee $employee): string
    {
        $parts = [];
        if ($employee->position) {
            $parts[] = $employee->position;
        } elseif ($employee->user) {
            $parts[] = $this->roleLabel((string) $employee->user->role);
        }
        if ($employee->store) {
            $parts[] = 'точка «'.$employee->store->name.'»';
        }

        return $parts !== [] ? implode(', ', $parts) : 'сотрудник';
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'super-admin' => 'супер-админ',
            'manager' => 'менеджер',
            'appraiser' => 'оценщик',
            'cashier' => 'кассир',
            'storekeeper' => 'кладовщик',
            default => $role,
        };
    }
}
