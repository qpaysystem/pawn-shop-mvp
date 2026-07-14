<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramWebhookUpdateJob;
use App\Models\Setting;
use App\Services\TelegramInboxService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramInboxService $inbox): JsonResponse
    {
        $secret = (string) Setting::get('telegram_webhook_secret', '');
        if ($secret !== '') {
            $incoming = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', $request->query('secret', ''));
            if (! hash_equals($secret, $incoming)) {
                abort(403);
            }
        }

        $payload = $this->decodePayload($request);
        if (! TelegramService::shouldQueueUpdate($payload)) {
            return response()->json(['ok' => true]);
        }

        $this->processUpdate($payload, $inbox);

        return response()->json(['ok' => true]);
    }

    /** @param  array<string, mixed>  $payload */
    public static function processUpdate(array $payload, ?TelegramInboxService $inbox = null): void
    {
        $inbox ??= app(TelegramInboxService::class);
        $connection = (string) config('queue.default', 'sync');

        if ($connection === 'sync') {
            (new ProcessTelegramWebhookUpdateJob($payload))->handle($inbox);

            return;
        }

        ProcessTelegramWebhookUpdateJob::dispatch($payload)->onConnection('database');
    }

    /** @return array<string, mixed> */
    private function decodePayload(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw !== '') {
            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                if (is_array($data)) {
                    return $data;
                }
            } catch (\Throwable) {
                // fallback
            }
        }
        $fallback = $request->json()->all();

        return $fallback !== [] ? $fallback : $request->all();
    }
}
