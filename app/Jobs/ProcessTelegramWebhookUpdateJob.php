<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\TelegramMessage;
use App\Services\TelegramInboxService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Обработка Telegram Update после быстрого 200 OK вебхука. */
class ProcessTelegramWebhookUpdateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public array $payload
    ) {}

    public function handle(TelegramInboxService $inbox): void
    {
        $message = $this->payload['message'] ?? $this->payload['edited_message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $from = $message['from'] ?? null;
        if (is_array($from) && ! empty($from['is_bot'])) {
            $token = (string) Setting::get('telegram_bot_token', '');
            $ourBotId = TelegramService::getBotUserId($token);
            if (! $ourBotId || (int) ($from['id'] ?? 0) !== $ourBotId) {
                return;
            }
            $chat = $message['chat'] ?? [];
            $chatId = TelegramService::normalizeChatIdForStorage((string) ($chat['id'] ?? ''));
            $messageId = (int) ($message['message_id'] ?? 0);
            $text = isset($message['text']) ? (string) $message['text'] : null;
            $date = isset($message['date']) ? (int) $message['date'] : null;
            try {
                TelegramMessage::query()->firstOrCreate(
                    [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ],
                    [
                        'chat_type' => (string) ($chat['type'] ?? 'private'),
                        'from_user_id' => (int) $from['id'],
                        'from_first_name' => 'ИИ-агент',
                        'text' => $text,
                        'message_date' => $date ? now()->setTimestamp($date) : now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('telegram_bot_echo_store', ['error' => $e->getMessage()]);
            }

            return;
        }

        try {
            $inbox->ingestFromTelegramMessage($message);
        } catch (\Throwable $e) {
            Log::warning('telegram_inbox_ingest_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
