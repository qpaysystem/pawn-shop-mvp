<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Setting;
use App\Models\TelegramMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Список чатов и переписка Telegram для колл-центра. */
class CallCenterTelegramService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listChats(): array
    {
        $labels = $this->chatLabels();
        $byChat = [];

        $rows = TelegramMessage::query()
            ->orderByDesc('message_date')
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'chat_id', 'chat_type', 'from_first_name', 'from_username', 'text', 'caption', 'message_date', 'from_user_id']);

        foreach ($rows as $row) {
            $cid = (string) $row->chat_id;
            if (isset($byChat[$cid])) {
                continue;
            }
            $preview = $row->displayText();
            $byChat[$cid] = [
                'chat_id' => $cid,
                'title' => $this->resolveChatTitle($cid, $row->chat_type, $row, $labels),
                'chat_type' => (string) ($row->chat_type ?? 'private'),
                'last_message' => mb_substr($preview, 0, 120),
                'last_at' => $row->message_date ? Carbon::parse($row->message_date)->toIso8601String() : null,
                'last_at_human' => $row->message_date ? Carbon::parse($row->message_date)->format('d.m.Y H:i') : '',
            ];
        }

        foreach (TelegramService::configuredInboxChatIds() as $cid) {
            if (isset($byChat[$cid])) {
                continue;
            }
            $byChat[$cid] = [
                'chat_id' => $cid,
                'title' => $labels[$cid] ?? $this->fetchChatTitleFromApi($cid) ?? "Чат {$cid}",
                'chat_type' => 'supergroup',
                'last_message' => '',
                'last_at' => null,
                'last_at_human' => '',
            ];
        }

        $list = array_values($byChat);
        usort($list, function (array $a, array $b): int {
            $ta = $a['last_at'] ? strtotime((string) $a['last_at']) : 0;
            $tb = $b['last_at'] ? strtotime((string) $b['last_at']) : 0;

            return $tb <=> $ta;
        });

        return $list;
    }

    /**
     * @return array{messages: array<int, array<string, mixed>>, chat: array<string, mixed>}
     */
    public function messagesForChat(string $chatId, int $limit = 80): array
    {
        $variants = TelegramService::chatIdVariants($chatId);
        $limit = max(10, min(200, $limit));

        $rows = TelegramMessage::query()
            ->whereIn('chat_id', $variants)
            ->orderBy('message_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $labels = $this->chatLabels();
        $first = $rows->first();
        $chatType = $first?->chat_type ?? 'private';

        $messages = [];
        foreach ($rows as $row) {
            $text = $row->displayText();
            if ($text === '' && $row->message_type) {
                $text = '['.$row->message_type.']';
            }
            $outgoing = $row->from_user_id === null && (
                str_contains((string) $row->from_first_name, 'ИИ-агент')
                || str_contains((string) $row->from_first_name, 'Ломбард')
            );

            $messages[] = [
                'id' => (int) $row->id,
                'message_id' => (int) $row->message_id,
                'text' => $text,
                'outgoing' => $outgoing,
                'sender' => $row->senderLabel(),
                'message_date' => $row->message_date ? Carbon::parse($row->message_date)->toIso8601String() : null,
                'time' => $row->message_date ? Carbon::parse($row->message_date)->format('H:i d.m') : '',
            ];
        }

        return [
            'chat' => [
                'chat_id' => TelegramService::normalizeChatIdForStorage($chatId),
                'title' => $this->resolveChatTitle($chatId, $chatType, $first, $labels),
                'chat_type' => $chatType,
            ],
            'messages' => $messages,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, message?: array<string, mixed>}
     */
    public function sendMessage(string $chatId, string $text): array
    {
        $token = trim((string) Setting::get('telegram_bot_token', ''));
        if ($token === '') {
            return ['ok' => false, 'error' => 'Не задан telegram_bot_token в настройках.'];
        }

        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'Пустое сообщение.'];
        }

        $chatId = TelegramService::normalizeChatIdForStorage($chatId);
        $result = TelegramService::sendPlainMessage(
            $token,
            $chatId,
            $text,
            true,
            'Ломбард (КЦ)',
        );

        if (! ($result['ok'] ?? false)) {
            $error = trim((string) ($result['error'] ?? ''));
            if ($error === '') {
                $error = 'Telegram API не принял сообщение. Проверьте, что бот в чате.';
            }

            return ['ok' => false, 'error' => $error];
        }

        $storedChatId = (string) ($result['chat_id'] ?? $chatId);
        $row = TelegramMessage::query()
            ->where('chat_id', $storedChatId)
            ->orderByDesc('id')
            ->first();

        return [
            'ok' => true,
            'message' => $row ? [
                'id' => (int) $row->id,
                'text' => $row->displayText(),
                'outgoing' => true,
                'sender' => $row->senderLabel(),
                'time' => $row->message_date ? Carbon::parse($row->message_date)->format('H:i d.m') : now()->format('H:i d.m'),
            ] : null,
        ];
    }

    public function isConfigured(): bool
    {
        return trim((string) Setting::get('telegram_bot_token', '')) !== '';
    }

    /**
     * Поиск: чаты, клиенты CRM, контакты из переписки, новый диалог по @username / id.
     *
     * @return array{ok: bool, query: string, sections: array<int, array{key: string, title: string, items: array<int, array<string, mixed>>}>}
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['ok' => true, 'query' => $query, 'sections' => []];
        }

        $qLower = mb_strtolower($query);
        $sections = [];

        $chatItems = [];
        foreach ($this->listChats() as $chat) {
            $hay = mb_strtolower(implode(' ', [
                (string) ($chat['title'] ?? ''),
                (string) ($chat['last_message'] ?? ''),
                (string) ($chat['chat_id'] ?? ''),
            ]));
            if (! str_contains($hay, $qLower)) {
                continue;
            }
            $chatItems[] = [
                'kind' => 'chat',
                'chat_id' => (string) $chat['chat_id'],
                'title' => (string) $chat['title'],
                'subtitle' => (string) ($chat['last_message'] ?: $chat['chat_type'] ?? ''),
                'chat_type' => (string) ($chat['chat_type'] ?? ''),
            ];
        }
        if ($chatItems !== []) {
            $sections[] = ['key' => 'chats', 'title' => 'Чаты', 'items' => array_slice($chatItems, 0, 15)];
        }

        $clientItems = [];
        $clients = Client::query()
            ->where(function ($inner) use ($query, $qLower) {
                $inner->matchingSearch($query)
                    ->orWhere('telegram_username', 'like', '%'.$query.'%')
                    ->orWhere('telegram_id', 'like', '%'.preg_replace('/\D/', '', $query).'%');
            })
            ->orderByDesc('telegram_id')
            ->limit(20)
            ->get(['id', 'full_name', 'phone', 'telegram_id', 'telegram_username']);

        foreach ($clients as $client) {
            $chatId = $this->clientTelegramChatId($client);
            $tgUser = $client->telegram_username ? '@'.ltrim((string) $client->telegram_username, '@') : null;
            $subtitle = trim(implode(' · ', array_filter([
                $client->phone,
                $tgUser,
                $chatId ? "ID {$chatId}" : null,
            ])));
            $clientItems[] = [
                'kind' => 'client',
                'client_id' => (int) $client->id,
                'chat_id' => $chatId,
                'title' => (string) $client->full_name,
                'subtitle' => $subtitle,
                'can_open' => $chatId !== null,
                'client_url' => route('clients.show', $client),
            ];
        }
        if ($clientItems !== []) {
            $sections[] = ['key' => 'clients', 'title' => 'Клиенты', 'items' => $clientItems];
        }

        $contactItems = [];
        $privateRows = TelegramMessage::query()
            ->where('chat_type', 'private')
            ->where(function ($inner) use ($query) {
                $inner->where('from_first_name', 'like', '%'.$query.'%')
                    ->orWhere('from_username', 'like', '%'.$query.'%')
                    ->orWhere('chat_id', 'like', '%'.$query.'%');
            })
            ->orderByDesc('message_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['chat_id', 'from_first_name', 'from_username', 'text', 'caption']);

        $seenPrivate = [];
        foreach ($privateRows as $row) {
            $cid = (string) $row->chat_id;
            if (isset($seenPrivate[$cid])) {
                continue;
            }
            $seenPrivate[$cid] = true;
            $name = trim((string) ($row->from_first_name ?? ''));
            if ($name === '' || str_contains($name, 'ИИ-агент')) {
                $name = $row->from_username ? '@'.ltrim((string) $row->from_username, '@') : "Чат {$cid}";
            }
            $contactItems[] = [
                'kind' => 'contact',
                'chat_id' => $cid,
                'title' => $name,
                'subtitle' => mb_substr($row->displayText(), 0, 80),
                'chat_type' => 'private',
            ];
        }
        if ($contactItems !== []) {
            $sections[] = ['key' => 'contacts', 'title' => 'Контакты Telegram', 'items' => array_slice($contactItems, 0, 15)];
        }

        $dialogTarget = TelegramService::parseDialogTarget($query);
        if ($dialogTarget !== null) {
            $known = false;
            foreach ($chatItems as $item) {
                if (($item['chat_id'] ?? '') === $dialogTarget || str_contains(mb_strtolower((string) ($item['title'] ?? '')), ltrim($dialogTarget, '@'))) {
                    $known = true;
                    break;
                }
            }
            if (! $known) {
                $sections[] = [
                    'key' => 'new_dialog',
                    'title' => 'Начать диалог',
                    'items' => [[
                        'kind' => 'new_dialog',
                        'target' => $dialogTarget,
                        'title' => str_starts_with($dialogTarget, '@') ? $dialogTarget : "ID {$dialogTarget}",
                        'subtitle' => 'Открыть чат в Telegram (нужен доступ бота)',
                    ]],
                ];
            }
        }

        return ['ok' => true, 'query' => $query, 'sections' => $sections];
    }

    /**
     * Открыть чат по chat_id, @username или client_id.
     *
     * @return array{ok: bool, error?: string, chat?: array<string, mixed>}
     */
    public function openChat(string $target, ?int $clientId = null): array
    {
        if ($clientId !== null && $clientId > 0) {
            $client = Client::query()->find($clientId);
            if (! $client) {
                return ['ok' => false, 'error' => 'Клиент не найден.'];
            }
            $chatId = $this->clientTelegramChatId($client);
            if ($chatId === null) {
                return ['ok' => false, 'error' => 'У клиента не указан Telegram (@username или ID).'];
            }
            $target = $chatId;
        }

        $target = trim($target);
        if ($target === '') {
            return ['ok' => false, 'error' => 'Укажите @username, ID чата или клиента.'];
        }

        if (TelegramService::parseDialogTarget($target) !== null) {
            $resolved = TelegramService::fetchChat($target);
            if (! ($resolved['ok'] ?? false)) {
                return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'Не удалось открыть чат.')];
            }
            $chat = $resolved['chat'];

            return [
                'ok' => true,
                'chat' => [
                    'chat_id' => (string) $chat['id'],
                    'title' => (string) $chat['title'],
                    'chat_type' => (string) $chat['type'],
                    'username' => $chat['username'] ?? null,
                ],
            ];
        }

        $chatId = TelegramService::normalizeChatIdForStorage($target);
        $labels = $this->chatLabels();
        $sample = TelegramMessage::query()
            ->whereIn('chat_id', TelegramService::chatIdVariants($chatId))
            ->orderByDesc('id')
            ->first();

        return [
            'ok' => true,
            'chat' => [
                'chat_id' => $chatId,
                'title' => $this->resolveChatTitle($chatId, $sample?->chat_type ?? 'private', $sample, $labels),
                'chat_type' => (string) ($sample?->chat_type ?? 'private'),
            ],
        ];
    }

    private function clientTelegramChatId(Client $client): ?string
    {
        if ($client->telegram_id) {
            return (string) (int) $client->telegram_id;
        }
        if ($client->telegram_username) {
            return '@'.ltrim((string) $client->telegram_username, '@');
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function chatLabels(): array
    {
        $raw = Setting::get('telegram_chat_titles', '');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $k => $v) {
                    $cid = TelegramService::normalizeChatIdForStorage((string) $k);
                    $title = trim((string) $v);
                    if ($cid !== '' && $title !== '') {
                        $out[$cid] = $title;
                    }
                }

                return $out;
            }
        }

        return $this->defaultChatLabels();
    }

    /**
     * @return array<string, string>
     */
    private function defaultChatLabels(): array
    {
        return [
            '-1001662424115' => 'Уведомления ломбард',
            '-3966230157' => 'Общая группа сотрудников',
            '-1003554201974' => 'Оценка залогов',
            '-5174785782' => 'Фронт-офис / видео',
        ];
    }

    private function resolveChatTitle(string $chatId, ?string $chatType, ?TelegramMessage $sample, array $labels): string
    {
        $cid = TelegramService::normalizeChatIdForStorage($chatId);
        foreach (TelegramService::chatIdVariants($cid) as $variant) {
            if (isset($labels[$variant])) {
                return $labels[$variant];
            }
        }

        $apiTitle = $this->fetchChatTitleFromApi($cid);
        if ($apiTitle) {
            return $apiTitle;
        }

        if ($chatType === 'private' && $sample) {
            $name = trim((string) ($sample->from_first_name ?? ''));
            if ($name !== '' && ! str_contains($name, 'ИИ-агент')) {
                return $name;
            }
        }

        return $chatType === 'private' ? "Личный чат {$cid}" : "Группа {$cid}";
    }

    private function fetchChatTitleFromApi(string $chatId): ?string
    {
        $token = trim((string) Setting::get('telegram_bot_token', ''));
        if ($token === '') {
            return null;
        }

        $cacheKey = 'tg_chat_title_'.hash('sha256', $chatId);

        return Cache::remember($cacheKey, 3600, function () use ($token, $chatId) {
            try {
                $r = TelegramService::httpClient(12)->get("https://api.telegram.org/bot{$token}/getChat", ['chat_id' => $chatId]);
                if (! $r->successful()) {
                    return null;
                }
                $result = $r->json('result');
                if (! is_array($result)) {
                    return null;
                }
                $title = trim((string) ($result['title'] ?? $result['username'] ?? $result['first_name'] ?? ''));

                return $title !== '' ? $title : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
