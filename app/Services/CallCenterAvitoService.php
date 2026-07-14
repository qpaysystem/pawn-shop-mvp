<?php

namespace App\Services;

use App\Services\Avito\AvitoApiService;
use App\Services\Avito\AvitoBranchConfig;
use Illuminate\Support\Carbon;

/** Колл-центр: чаты Avito по объявлениям и филиалам. */
class CallCenterAvitoService
{
    public function __construct(
        private AvitoApiService $api
    ) {}

    public function isConfigured(): bool
    {
        return AvitoBranchConfig::isConfigured();
    }

    public function defaultBranchSlug(): string
    {
        foreach (AvitoBranchConfig::branches() as $slug => $branch) {
            if (! empty($branch['user_id'])) {
                return $slug;
            }
        }

        return 'kolhidskaya';
    }

    /**
     * @return array<int, array{slug: string, label: string, configured: bool}>
     */
    public function branchesForUi(): array
    {
        $out = [];
        foreach (AvitoBranchConfig::branches() as $branch) {
            $out[] = [
                'slug' => $branch['slug'],
                'label' => $branch['label'],
                'configured' => ! empty($branch['user_id']),
            ];
        }

        return $out;
    }

    /**
     * @return array{ok: bool, error?: string, branch?: string, chats?: array<int, array<string, mixed>>, listings?: array<int, array<string, mixed>>}
     */
    public function listChats(string $branchSlug): array
    {
        $branch = $this->requireBranch($branchSlug);
        if (! ($branch['ok'] ?? false)) {
            return $branch;
        }

        $userId = (string) $branch['user_id'];
        $result = $this->api->listChats($userId, 100, 0);
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $chats = [];
        $byListing = [];

        foreach ($result['chats'] ?? [] as $raw) {
            $normalized = $this->normalizeChatRow($raw, $branchSlug);
            $chats[] = $normalized;

            $itemId = (string) ($normalized['item_id'] ?? '');
            $listingKey = $itemId !== '' ? $itemId : '_no_item';
            if (! isset($byListing[$listingKey])) {
                $byListing[$listingKey] = [
                    'item_id' => $itemId !== '' ? $itemId : null,
                    'item_title' => (string) ($normalized['item_title'] ?? 'Без объявления'),
                    'item_price' => $normalized['item_price'] ?? null,
                    'item_url' => $normalized['item_url'] ?? null,
                    'chats' => [],
                ];
            }
            $byListing[$listingKey]['chats'][] = $normalized;
        }

        usort($chats, fn (array $a, array $b): int => ($b['last_at_ts'] ?? 0) <=> ($a['last_at_ts'] ?? 0));

        $listings = array_values($byListing);
        usort($listings, function (array $a, array $b): int {
            $ta = 0;
            $tb = 0;
            foreach ($a['chats'] as $c) {
                $ta = max($ta, (int) ($c['last_at_ts'] ?? 0));
            }
            foreach ($b['chats'] as $c) {
                $tb = max($tb, (int) ($c['last_at_ts'] ?? 0));
            }

            return $tb <=> $ta;
        });

        return [
            'ok' => true,
            'branch' => $branchSlug,
            'chats' => $chats,
            'listings' => $listings,
        ];
    }

    /**
     * Все чаты филиала (с пагинацией API).
     *
     * @return array{ok: bool, error?: string, chats?: array<int, array<string, mixed>>}
     */
    public function listAllChats(string $branchSlug, int $pageSize = 100, int $maxPages = 100): array
    {
        $branch = $this->requireBranch($branchSlug);
        if (! ($branch['ok'] ?? false)) {
            return $branch;
        }

        $userId = (string) $branch['user_id'];
        $all = [];
        $offset = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $result = $this->api->listChats($userId, $pageSize, $offset);
            if (! ($result['ok'] ?? false)) {
                if ($all === []) {
                    return $result;
                }

                return ['ok' => true, 'chats' => $all, 'warning' => $result['error'] ?? 'частичная загрузка'];
            }

            $batch = $result['chats'] ?? [];
            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $raw) {
                if (is_array($raw)) {
                    $all[] = $this->normalizeChatRow($raw, $branchSlug);
                }
            }

            if (count($batch) < $pageSize) {
                break;
            }

            $offset += $pageSize;
            usleep(150_000);
        }

        return ['ok' => true, 'chats' => $all];
    }

    /**
     * Все сообщения чата (с пагинацией API).
     *
     * @return array{ok: bool, error?: string, chat?: array<string, mixed>, messages?: array<int, array<string, mixed>>}
     */
    public function listAllMessagesForChat(string $branchSlug, string $chatId, int $pageSize = 100, int $maxPages = 50): array
    {
        $branch = $this->requireBranch($branchSlug);
        if (! ($branch['ok'] ?? false)) {
            return $branch;
        }

        $userId = (string) $branch['user_id'];
        $all = [];
        $offset = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $result = $this->api->listMessages($userId, $chatId, $pageSize, $offset);
            if (! ($result['ok'] ?? false)) {
                if ($all === []) {
                    return $result;
                }
                break;
            }

            $batch = $result['messages'] ?? [];
            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $raw) {
                if (is_array($raw)) {
                    $all[] = $this->normalizeMessageRow($raw);
                }
            }

            if (count($batch) < $pageSize) {
                break;
            }

            $offset += $pageSize;
            usleep(100_000);
        }

        usort($all, fn (array $a, array $b): int => ($a['sort_ts'] ?? 0) <=> ($b['sort_ts'] ?? 0));

        $chatMeta = $this->findChatMeta($branchSlug, $chatId);

        return [
            'ok' => true,
            'chat' => $chatMeta ?? [
                'chat_id' => $chatId,
                'title' => 'Чат Avito',
                'item_title' => '',
            ],
            'messages' => $all,
        ];
    }

    public function normalizeChatFromRaw(array $raw, string $branchSlug): array
    {
        return $this->normalizeChatRow($raw, $branchSlug);
    }

    public function normalizeMessageFromRaw(array $raw): array
    {
        return $this->normalizeMessageRow($raw);
    }

    /**
     * @return array{ok: bool, error?: string, chat?: array<string, mixed>, messages?: array<int, array<string, mixed>>}
     */
    public function messagesForChat(string $branchSlug, string $chatId, int $limit = 80): array
    {
        $branch = $this->requireBranch($branchSlug);
        if (! ($branch['ok'] ?? false)) {
            return $branch;
        }

        $userId = (string) $branch['user_id'];
        $result = $this->api->listMessages($userId, $chatId, $limit, 0);
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $messages = [];
        foreach ($result['messages'] ?? [] as $raw) {
            $messages[] = $this->normalizeMessageRow($raw);
        }

        usort($messages, function (array $a, array $b): int {
            return ($a['sort_ts'] ?? 0) <=> ($b['sort_ts'] ?? 0);
        });

        $chatMeta = $this->findChatMeta($branchSlug, $chatId);

        return [
            'ok' => true,
            'chat' => $chatMeta ?? [
                'chat_id' => $chatId,
                'title' => 'Чат Avito',
                'item_title' => '',
            ],
            'messages' => $messages,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, message?: array<string, mixed>}
     */
    public function sendMessage(string $branchSlug, string $chatId, string $text): array
    {
        $branch = $this->requireBranch($branchSlug);
        if (! ($branch['ok'] ?? false)) {
            return $branch;
        }

        $userId = (string) $branch['user_id'];
        $result = $this->api->sendTextMessage($userId, $chatId, $text);
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        return [
            'ok' => true,
            'message' => [
                'text' => trim($text),
                'outgoing' => true,
                'sender' => 'Ломбард (КЦ)',
                'time' => now()->format('H:i d.m'),
            ],
        ];
    }

    /**
     * @return array{ok: bool, error?: string, user_id?: string}
     */
    private function requireBranch(string $branchSlug): array
    {
        $branch = AvitoBranchConfig::branch($branchSlug);
        if ($branch === null) {
            return ['ok' => false, 'error' => 'Неизвестный филиал.'];
        }
        if (empty($branch['user_id'])) {
            return ['ok' => false, 'error' => 'Для филиала «'.$branch['label'].'» не задан Avito user_id в настройках.'];
        }

        return ['ok' => true, 'user_id' => (string) $branch['user_id'], 'label' => $branch['label']];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeChatRow(array $raw, string $branchSlug): array
    {
        $chatId = (string) ($raw['id'] ?? '');
        $context = is_array($raw['context'] ?? null) ? $raw['context'] : [];
        $ctxValue = is_array($context['value'] ?? null) ? $context['value'] : [];
        $itemId = isset($ctxValue['id']) ? (string) $ctxValue['id'] : '';
        $itemTitle = trim((string) ($ctxValue['title'] ?? ''));
        $itemUrl = trim((string) ($ctxValue['url'] ?? ''));
        $itemPrice = $ctxValue['price_string'] ?? ($ctxValue['price'] ?? null);

        $users = is_array($raw['users'] ?? null) ? $raw['users'] : [];
        $peerName = '';
        foreach ($users as $u) {
            if (! is_array($u)) {
                continue;
            }
            $name = trim((string) ($u['name'] ?? $u['public_user_profile']['name'] ?? ''));
            if ($name !== '') {
                $peerName = $name;
                break;
            }
        }

        $last = is_array($raw['last_message'] ?? null) ? $raw['last_message'] : [];
        $preview = $this->messagePreview($last);
        $lastAt = $this->messageTimestamp($last);
        $direction = (string) ($last['direction'] ?? '');

        return [
            'chat_id' => $chatId,
            'branch' => $branchSlug,
            'peer_name' => $peerName !== '' ? $peerName : 'Покупатель',
            'title' => $peerName !== '' ? $peerName : 'Чат '.$chatId,
            'item_id' => $itemId,
            'item_title' => $itemTitle !== '' ? $itemTitle : 'Объявление',
            'item_price' => is_scalar($itemPrice) ? (string) $itemPrice : null,
            'item_url' => $itemUrl !== '' ? $itemUrl : null,
            'last_message' => $preview,
            'last_at' => $lastAt ? Carbon::createFromTimestamp($lastAt)->toIso8601String() : null,
            'last_at_human' => $lastAt ? Carbon::createFromTimestamp($lastAt)->format('d.m.Y H:i') : '',
            'last_at_ts' => $lastAt ?? 0,
            'unread' => ($raw['unread_count'] ?? 0) > 0 || $direction === 'in',
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeMessageRow(array $raw): array
    {
        $content = is_array($raw['content'] ?? null) ? $raw['content'] : [];
        $text = trim((string) ($content['text'] ?? $raw['text'] ?? ''));
        if ($text === '' && isset($raw['type'])) {
            $text = '['.(string) $raw['type'].']';
        }

        $ts = $this->messageTimestamp($raw);
        $direction = (string) ($raw['direction'] ?? '');
        $outgoing = $direction === 'out';

        return [
            'id' => (string) ($raw['id'] ?? ''),
            'text' => $text,
            'outgoing' => $outgoing,
            'sender' => $outgoing ? 'Ломбард' : 'Клиент',
            'time' => $ts ? Carbon::createFromTimestamp($ts)->format('H:i d.m') : '',
            'sort_ts' => $ts ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messagePreview(array $message): string
    {
        $text = $this->messagePreviewText($message);

        return mb_substr($text, 0, 120);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messagePreviewText(array $message): string
    {
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];
        $text = trim((string) ($content['text'] ?? $message['text'] ?? ''));
        if ($text !== '') {
            return $text;
        }
        $type = (string) ($message['type'] ?? '');

        return $type !== '' ? '['.$type.']' : '';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messageTimestamp(array $message): ?int
    {
        foreach (['created', 'created_at', 'published_at'] as $key) {
            $val = $message[$key] ?? null;
            if (is_numeric($val)) {
                return (int) $val;
            }
            if (is_string($val) && $val !== '') {
                $ts = strtotime($val);

                return $ts !== false ? $ts : null;
            }
        }

        return null;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function findChatMeta(string $branchSlug, string $chatId): ?array
    {
        $list = $this->listChats($branchSlug);
        if (! ($list['ok'] ?? false)) {
            return null;
        }
        foreach ($list['chats'] ?? [] as $chat) {
            if (($chat['chat_id'] ?? '') === $chatId) {
                return $chat;
            }
        }

        return null;
    }
}
