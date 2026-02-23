<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Транскрипция записи разговора: Whisper (OpenAI) — аудио в текст, DeepSeek — оформление текста.
 */
class CallRecordingTranscriptionService
{
    /**
     * Транскрибировать аудиофайл и оформить текст через DeepSeek.
     * Возвращает итоговый текст или null при ошибке.
     */
    public function transcribeAndFormat(string $audioPath): ?string
    {
        $raw = $this->transcribeWithWhisper($audioPath);
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $formatted = $this->formatWithDeepSeek($raw);
        return $formatted !== null ? $formatted : $raw;
    }

    /**
     * Распознавание речи через OpenAI Whisper.
     */
    public function transcribeWithWhisper(string $audioPath): ?string
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey) || ! is_readable($audioPath)) {
            Log::debug('CallRecordingTranscription: пропуск Whisper', ['reason' => empty($apiKey) ? 'no key' : 'file unreadable']);
            return null;
        }

        $response = Http::timeout(120)
            ->withToken($apiKey)
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'response_format' => 'text',
                'language' => 'ru',
            ]);

        if (! $response->successful()) {
            Log::warning('Whisper API ошибка', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $text = $response->body();
        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /**
     * Оформление сырой расшифровки через DeepSeek: пунктуация, абзацы по репликам.
     */
    public function formatWithDeepSeek(string $rawTranscript): ?string
    {
        $apiKey = config('services.deepseek.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $prompt = <<<PROMPT
Ниже сырая расшифровка телефонного разговора (без знаков препинания). Отформатируй текст:
- расставь знаки препинания;
- разбей по репликам, каждую с новой строки (можно помечать как "Клиент:" / "Оператор:" если очевидно, иначе просто реплики друг за другом);
- не добавляй комментариев и заголовков — только итоговый текст расшифровки.

Текст для обработки:

{$rawTranscript}
PROMPT;

        try {
            $model = config('services.deepseek.model', 'deepseek-chat');
            $response = Http::timeout(60)
                ->withToken($apiKey)
                ->post('https://api.deepseek.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 4096,
                ]);

            if (! $response->successful()) {
                Log::warning('DeepSeek API ошибка (транскрипция)', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            return $content !== null && trim((string) $content) !== '' ? trim((string) $content) : null;
        } catch (\Throwable $e) {
            Log::warning('DeepSeek при оформлении транскрипции', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
