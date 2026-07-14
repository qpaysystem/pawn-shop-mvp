<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramWebhookController;
use App\Models\Setting;
use App\Services\TelegramInboxService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/** Long polling Telegram (если webhook недоступен снаружи). */
class TelegramLongPollCommand extends Command
{
    protected $signature = 'telegram:poll
                            {--delete-webhook : Удалить webhook перед poll}
                            {--timeout=50 : Long poll timeout сек}';

    protected $description = 'Telegram long poll → inbox ломбарда';

    public function handle(): int
    {
        $token = trim((string) Setting::get('telegram_bot_token', ''));
        if ($token === '') {
            $this->error('Задайте telegram_bot_token в настройках.');

            return self::FAILURE;
        }

        if ($this->option('delete-webhook')) {
            TelegramService::httpClient(15)->get("https://api.telegram.org/bot{$token}/deleteWebhook");
            $this->info('Webhook удалён.');
        }

        $offset = 0;
        $timeout = max(10, min(50, (int) $this->option('timeout')));
        $this->info("Long poll (timeout {$timeout}s). Ctrl+C для выхода.");

        while (true) {
            try {
                $response = TelegramService::httpClient($timeout + 15)->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => $timeout,
                    'allowed_updates' => json_encode(['message', 'edited_message']),
                ]);
            } catch (\Throwable $e) {
                $this->warn('getUpdates: '.$e->getMessage());
                sleep(3);

                continue;
            }

            if (! $response->successful()) {
                $this->warn('HTTP '.$response->status());
                sleep(3);

                continue;
            }

            $updates = $response->json('result');
            if (! is_array($updates)) {
                continue;
            }

            foreach ($updates as $update) {
                if (! is_array($update)) {
                    continue;
                }
                $updateId = (int) ($update['update_id'] ?? 0);
                $offset = max($offset, $updateId + 1);
                if (! TelegramService::shouldQueueUpdate($update)) {
                    continue;
                }
                TelegramWebhookController::processUpdate($update, app(TelegramInboxService::class));
                $this->line('Processed update '.$updateId);
            }
        }
    }
}
