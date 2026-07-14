<?php

namespace App\Services\Meetings;

use App\Models\MeetingReport;
use App\Services\Acuerdo\AcuerdoConfClient;
use App\Support\AcuerdoCredentials;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/** Загрузка записи с conf.nnfm.pro → транскрипция → отчёт в журнал. */
class MeetingReportSyncService
{
    public function __construct(
        private AcuerdoConfClient $conf = new AcuerdoConfClient,
        private MeetingTranscriptionService $transcription = new MeetingTranscriptionService,
        private MeetingSummaryService $summary = new MeetingSummaryService,
    ) {}

    /**
     * @return array{ok: bool, error?: string, report?: MeetingReport, skipped?: string}
     */
    public function syncLatest(bool $force = false): array
    {
        if (! AcuerdoCredentials::isConfigured()) {
            return ['ok' => false, 'error' => 'Не заданы учётные данные Acuerdo.'];
        }

        $room = trim((string) config('services.acuerdo.meeting_room', 'Комната совещаний'));
        if (! $this->conf->login()) {
            return ['ok' => false, 'error' => 'Не удалось войти на conf.nnfm.pro.'];
        }

        $recordings = $this->conf->listRecordings($room);
        if ($recordings === []) {
            return ['ok' => false, 'error' => 'Записи собраний не найдены.'];
        }

        $recording = $recordings[0];
        $existing = MeetingReport::query()->where('file_ref', $recording['file_ref'])->first();
        if ($existing !== null && $existing->isCompleted() && ! $force) {
            return ['ok' => true, 'skipped' => 'already_processed', 'report' => $existing];
        }

        return $this->processRecording($recording, $room, $existing, $force);
    }

    /**
     * @param  array{title: string, file_ref: string, meeting_at: Carbon}  $recording
     * @return array{ok: bool, error?: string, report?: MeetingReport}
     */
    private function processRecording(array $recording, string $room, ?MeetingReport $report, bool $force): array
    {
        $report ??= MeetingReport::query()->firstOrNew(['file_ref' => $recording['file_ref']]);
        $report->fill([
            'business_date' => $recording['meeting_at']->toDateString(),
            'meeting_at' => $recording['meeting_at'],
            'room' => mb_substr($room, 0, 256),
            'title' => mb_substr($recording['title'], 0, 512),
            'status' => MeetingReport::STATUS_PROCESSING,
            'error_message' => null,
        ]);
        $report->save();

        $workDir = storage_path('app/meetings/'.preg_replace('/[^\w.-]+/', '_', $recording['file_ref']));
        if (! is_dir($workDir) && ! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            return $this->fail($report, 'Не удалось создать рабочую папку.');
        }

        $videoPath = $workDir.'/recording.mp4';

        try {
            if (! $this->conf->login()) {
                return $this->fail($report, 'Не удалось войти на conf.nnfm.pro для скачивания.');
            }
            $this->conf->downloadVideo($recording['file_ref'], $videoPath);
        } catch (\Throwable $e) {
            Log::warning('meeting download failed', ['file' => $recording['file_ref'], 'error' => $e->getMessage()]);

            return $this->fail($report, 'Ошибка скачивания видео: '.$e->getMessage());
        }

        $transcriptResult = $this->transcription->transcribeVideo($videoPath);
        if ($transcriptResult['error'] !== null) {
            return $this->fail($report, $transcriptResult['error']);
        }

        $formatted = (string) ($transcriptResult['formatted'] ?? '');
        $summaryData = $this->summary->summarize($formatted, $recording['title'], $room);

        $report->fill([
            'transcript_raw' => mb_substr((string) ($transcriptResult['raw'] ?? ''), 0, 500000),
            'transcript' => mb_substr($formatted, 0, 500000),
            'summary' => $summaryData['summary'],
            'highlights' => $summaryData['highlights'],
            'status' => MeetingReport::STATUS_COMPLETED,
            'processed_at' => now(),
            'error_message' => null,
        ]);
        $report->save();

        @unlink($videoPath);
        @unlink(preg_replace('/\.[^.]+$/', '.mp3', $videoPath) ?? '');

        return ['ok' => true, 'report' => $report->fresh()];
    }

    /** @return array{ok: false, error: string, report?: MeetingReport} */
    private function fail(MeetingReport $report, string $message): array
    {
        $report->fill([
            'status' => MeetingReport::STATUS_FAILED,
            'error_message' => $message,
        ]);
        $report->save();

        return ['ok' => false, 'error' => $message, 'report' => $report->fresh()];
    }
}
