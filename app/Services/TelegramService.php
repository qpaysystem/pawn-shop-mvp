<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Telegram Bot API: inbox ломбарда и исходящие ответы. */
class TelegramService
{
    public static function normalizeChatIdForStorage(string $id): string
    {
        return trim(str_replace(["\xc2\xa0", ' '], '', $id));
    }

    /**
     * @return array<int, string>
     */
    public static function chatIdVariants(string $id): array
    {
        $s = self::normalizeChatIdForStorage($id);
        if ($s === '' || ! str_starts_with($s, '-')) {
            return $s !== '' ? [$s] : [];
        }
        $rest = ltrim($s, '-');
        if (! ctype_digit($rest)) {
            return [$s];
        }
        $out = [$s];
        if (str_starts_with($s, '-100') && strlen($rest) > 3) {
            $tail = substr($rest, 3);
            if ($tail !== '' && ctype_digit($tail)) {
                $out[] = '-'.$tail;
            }
        } elseif (strlen($rest) >= 8) {
            $out[] = '-100'.$rest;
        }

        return array_values(array_unique($out));
    }

    public static function chatIdInList(string $chatId, array $allowed): bool
    {
        if ($allowed === []) {
            return false;
        }
        foreach (self::chatIdVariants($chatId) as $variant) {
            if (in_array($variant, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    public static function truncatePlainMessage(string $text, int $maxLen = 4000): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen - 20)."\n…(обрезано)";
    }

    /**
     * @return array<int, string>
     */
    public static function configuredInboxChatIds(): array
    {
        $raw = [
            (string) Setting::get('telegram_inbox_chat_id', ''),
            (string) Setting::get('telegram_chat_id', ''),
        ];
        $out = [];
        foreach ($raw as $chunk) {
            foreach (preg_split('/[\s,;]+/', trim($chunk), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
                $norm = self::normalizeChatIdForStorage($id);
                if ($norm !== '') {
                    $out[] = $norm;
                }
            }
        }

        return array_values(array_unique($out));
    }

    public static function privateInboxEnabled(): bool
    {
        return Setting::get('telegram_private_inbox_enabled', '1') === '1';
    }

    /**
     * Outbound proxy for Telegram Bot API (VPS, как в CRM / agent-teams tg-bridge).
     *
     * @return array<int, string>
     */
    private static function telegramProxyEnvKeys(): array
    {
        return [
            'TELEGRAM_PROXY',
            'TELEGRAM_HTTPS_PROXY',
            'TELEGRAM_HTTP_PROXY',
            'HTTPS_PROXY',
            'HTTP_PROXY',
            'OPENAI_HTTP_PROXY',
        ];
    }

    public static function telegramProxy(): ?string
    {
        foreach (self::telegramProxyEnvKeys() as $key) {
            $value = trim((string) env($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        $fromConfig = trim((string) config('services.openai.http_proxy', ''));

        return $fromConfig !== '' ? $fromConfig : null;
    }

    public static function httpClient(int $timeout = 15): \Illuminate\Http\Client\PendingRequest
    {
        $req = Http::connectTimeout(10)->timeout($timeout);
        $proxy = self::telegramProxy();
        if ($proxy) {
            return $req->withOptions(['proxy' => $proxy]);
        }

        return $req;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function shouldQueueUpdate(array $payload): bool
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? null;
        if (! is_array($message)) {
            return false;
        }
        $chat = $message['chat'] ?? null;
        if (! is_array($chat)) {
            return false;
        }
        $chatType = (string) ($chat['type'] ?? '');
        $chatId = self::normalizeChatIdForStorage((string) ($chat['id'] ?? ''));
        if ($chatId === '') {
            return false;
        }

        $from = $message['from'] ?? null;
        if (is_array($from) && ! empty($from['is_bot'])) {
            $token = (string) Setting::get('telegram_bot_token', '');
            $ourBotId = self::getBotUserId($token);

            return $ourBotId && (int) ($from['id'] ?? 0) === $ourBotId;
        }

        if (in_array($chatType, ['group', 'supergroup'], true)) {
            $allowed = self::configuredInboxChatIds();

            return $allowed !== [] && self::chatIdInList($chatId, $allowed);
        }

        if ($chatType === 'private' && self::privateInboxEnabled()) {
            return true;
        }

        return false;
    }

    public static function getBotUserId(?string $token = null): ?int
    {
        $token = $token ?? (string) Setting::get('telegram_bot_token', '');
        if ($token === '') {
            return null;
        }
        $cacheKey = 'telegram_bot_user_id_'.hash('sha256', $token);

        return Cache::remember($cacheKey, 86400, function () use ($token) {
            try {
                $response = self::httpClient(10)->get("https://api.telegram.org/bot{$token}/getMe");
                if (! $response->successful()) {
                    return null;
                }
                $id = $response->json('result.id');

                return is_numeric($id) ? (int) $id : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * @return array{ok: bool, chat?: array{id: string, type: string, title: string, username: ?string}, error?: string}
     */
    public static function fetchChat(string $chatRef): array
    {
        $token = trim((string) Setting::get('telegram_bot_token', ''));
        if ($token === '') {
            return ['ok' => false, 'error' => 'Не задан telegram_bot_token.'];
        }

        $chatRef = trim($chatRef);
        if ($chatRef === '') {
            return ['ok' => false, 'error' => 'Пустой идентификатор чата.'];
        }

        if (str_starts_with($chatRef, '@')) {
            $chatParam = $chatRef;
        } elseif (preg_match('/^[a-zA-Z][\w]{4,31}$/', ltrim($chatRef, '@'))) {
            $chatParam = '@'.ltrim($chatRef, '@');
        } else {
            $chatParam = self::normalizeChatIdForStorage($chatRef);
        }

        try {
            $response = self::httpClient(12)->get("https://api.telegram.org/bot{$token}/getChat", [
                'chat_id' => $chatParam,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $decoded = $response->json();
        if (! $response->successful() || ! is_array($decoded) || ! ($decoded['ok'] ?? false)) {
            $error = is_array($decoded) ? trim((string) ($decoded['description'] ?? '')) : '';
            if ($error === '') {
                $error = 'Чат не найден или бот не имеет к нему доступа.';
            }

            return ['ok' => false, 'error' => $error];
        }

        $result = $decoded['result'] ?? null;
        if (! is_array($result) || ! isset($result['id'])) {
            return ['ok' => false, 'error' => 'Некорректный ответ Telegram API.'];
        }

        $type = (string) ($result['type'] ?? 'private');
        $username = isset($result['username']) ? (string) $result['username'] : null;
        $title = trim((string) ($result['title'] ?? ''));
        if ($title === '') {
            $first = trim((string) ($result['first_name'] ?? ''));
            $last = trim((string) ($result['last_name'] ?? ''));
            $title = trim($first.' '.$last);
        }
        if ($title === '' && $username) {
            $title = '@'.$username;
        }
        if ($title === '') {
            $title = 'Чат '.$result['id'];
        }

        return [
            'ok' => true,
            'chat' => [
                'id' => self::normalizeChatIdForStorage((string) $result['id']),
                'type' => $type,
                'title' => $title,
                'username' => $username,
            ],
        ];
    }

    /** @return ?string @username, числовой id или null */
    public static function parseDialogTarget(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, '@')) {
            $u = ltrim($raw, '@');
            if (preg_match('/^[a-zA-Z][\w]{4,31}$/', $u)) {
                return '@'.$u;
            }

            return null;
        }
        if (preg_match('/^[a-zA-Z][\w]{4,31}$/', $raw)) {
            return '@'.$raw;
        }
        $norm = self::normalizeChatIdForStorage($raw);
        if ($norm !== '' && preg_match('/^-?\d+$/', $norm)) {
            return $norm;
        }

        return null;
    }

    /**
     * @return array{ok: bool, error?: string, message_id?: int}
     */
    public static function sendPlainMessage(
        string $token,
        string $chatId,
        string $text,
        bool $recordInHistory = true,
        string $outgoingAuthorFirstName = 'ИИ-агент',
        ?int $replyToMessageId = null
    ): array {
        $chatId = self::normalizeChatIdForStorage($chatId);
        $payload = [
            'chat_id' => $chatId,
            'text' => self::truncatePlainMessage($text),
        ];
        if ($replyToMessageId !== null && $replyToMessageId > 0) {
            $payload['reply_to_message_id'] = $replyToMessageId;
        }

        try {
            $response = self::httpClient(20)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        } catch (\Throwable $e) {
            Log::warning('telegram_send_plain', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $decoded = $response->json();
        $ok = $response->successful() && is_array($decoded) && ($decoded['ok'] ?? false);
        if (! $ok) {
            $error = is_array($decoded) ? trim((string) ($decoded['description'] ?? '')) : '';
            if ($error === '') {
                $error = trim($response->body());
            }
            Log::warning('telegram_send_plain', [
                'error' => $error,
                'status' => $response->status(),
                'chat_id' => $chatId,
            ]);

            return ['ok' => false, 'error' => $error !== '' ? $error : 'Telegram API error'];
        }

        $msgId = isset($decoded['result']['message_id']) ? (int) $decoded['result']['message_id'] : null;
        $storedChatId = $chatId;
        if (isset($decoded['result']['chat']['id'])) {
            $storedChatId = self::normalizeChatIdForStorage((string) $decoded['result']['chat']['id']);
        }

        if ($recordInHistory) {
            TelegramMessage::recordBotOutgoing($storedChatId, $msgId, $payload['text'], $outgoingAuthorFirstName);
        }

        return ['ok' => true, 'message_id' => $msgId, 'chat_id' => $storedChatId];
    }
}
