<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\Client;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public static function sendTransactionNotification(BalanceTransaction $transaction): void
    {
        $client = $transaction->client;
        if (!$client) {
            return;
        }

        $amount = (float) $transaction->amount;
        $sign = $transaction->type === 'deposit' ? '+' : '−';
        $currency = \App\Models\Setting::get('currency', 'RUB');
        $operation = $transaction->operation_type_label ?? ($transaction->type === 'deposit' ? 'Пополнение' : 'Списание');
        $title = 'Проведена транзакция';
        $body = "{$operation}: {$sign}" . number_format(abs($amount), 2) . " {$currency}";
        if ($transaction->comment) {
            $body .= '. ' . \Illuminate\Support\Str::limit($transaction->comment, 60);
        }

        self::sendToClient($client->id, $title, $body, '/cabinet/transactions');
    }

    /** @param int $clientId Client ID */
    public static function sendToClient(int $clientId, string $title, string $body, string $url = '/cabinet'): void
    {
        if (!class_exists(WebPush::class)) {
            return;
        }

        $publicKey = config('services.vapid.public');
        $privateKey = config('services.vapid.private');
        if (!$publicKey || !$privateKey) {
            Log::debug('Push: VAPID keys not set');
            return;
        }

        $subscriptions = PushSubscription::where('client_id', $clientId)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);

        $auth = [
            'VAPID' => [
                'subject' => 'mailto:' . (config('mail.from.address') ?: 'admin@example.com'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        try {
            $webPush = new WebPush($auth);
            foreach ($subscriptions as $sub) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys' => [
                            'p256dh' => $sub->public_key,
                            'auth' => $sub->auth_token,
                        ],
                    ]);
                    $webPush->queueNotification($subscription, $payload);
                } catch (\Exception $e) {
                    Log::warning('Push subscription invalid: ' . $e->getMessage());
                }
            }
            $webPush->flush();
        } catch (\Exception $e) {
            Log::warning('Push send failed: ' . $e->getMessage());
        }
    }
}
