<?php

namespace App\Services\Avito;

use App\Models\AvitoMessage;
use App\Services\CallCenterAvitoService;
use Illuminate\Support\Facades\Cache;

/** Полная подгрузка чатов/сообщений Avito в локальную БД. */
class AvitoInboxSyncService
{
    public function __construct(
        private AvitoApiService $api,
        private CallCenterAvitoService $callCenter,
        private AvitoInboxService $inbox,
    ) {}

    /**
     * Синхронизировать обращения по всем настроенным филиалам.
     *
     * @return array{ok: bool, error?: string, branches?: list<array<string, mixed>>, totals?: array<string, int>}
     */
    public function syncActiveListingInquiries(?string $branchSlug = null): array
    {
        if (! AvitoBranchConfig::isConfigured()) {
            return ['ok' => false, 'error' => 'Avito API не настроен (client_id / client_secret / user_id филиала).'];
        }

        $activeListingIds = $this->fetchActiveListingIds();
        if ($activeListingIds === []) {
            return ['ok' => false, 'error' => 'Не удалось получить активные объявления Avito (пустой список или ошибка API).'];
        }

        $branches = $this->branchesToSync($branchSlug);
        if ($branches === []) {
            return ['ok' => false, 'error' => 'Нет настроенных филиалов Avito для синхронизации.'];
        }

        $branchResults = [];
        $totals = [
            'active_listings' => count($activeListingIds),
            'chats_matched' => 0,
            'chats_synced' => 0,
            'messages_ingested' => 0,
        ];

        foreach ($branches as $slug => $branch) {
            $result = $this->syncBranch($slug, $activeListingIds);
            $branchResults[] = $result;

            if ($result['ok'] ?? false) {
                $totals['chats_matched'] += (int) ($result['chats_matched'] ?? 0);
                $totals['chats_synced'] += (int) ($result['chats_synced'] ?? 0);
                $totals['messages_ingested'] += (int) ($result['messages_ingested'] ?? 0);
            }
        }

        Cache::forget('avito_active_items_for_life_map');

        return [
            'ok' => true,
            'branches' => $branchResults,
            'totals' => $totals,
        ];
    }

    /**
     * @param  array<string, true>  $activeListingIds
     * @return array<string, mixed>
     */
    private function syncBranch(string $branchSlug, array $activeListingIds): array
    {
        $incomingBefore = $this->countIncomingMessages();

        $chatsResult = $this->callCenter->listAllChats($branchSlug);
        if (! ($chatsResult['ok'] ?? false)) {
            return [
                'ok' => false,
                'branch' => $branchSlug,
                'error' => $chatsResult['error'] ?? 'Ошибка загрузки чатов',
            ];
        }

        $matched = [];
        foreach ($chatsResult['chats'] ?? [] as $chat) {
            $itemId = (string) ($chat['item_id'] ?? '');
            if ($itemId !== '' && isset($activeListingIds[$itemId])) {
                $matched[] = $chat;
            }
        }

        $messagesIngested = 0;

        foreach ($matched as $chatMeta) {
            $chatId = (string) ($chatMeta['chat_id'] ?? '');
            if ($chatId === '') {
                continue;
            }

            $this->inbox->upsertChat($branchSlug, $chatMeta);

            $msgResult = $this->callCenter->listAllMessagesForChat($branchSlug, $chatId);
            if (! ($msgResult['ok'] ?? false)) {
                continue;
            }

            $messages = $msgResult['messages'] ?? [];
            if (! is_array($messages) || $messages === []) {
                continue;
            }

            $chatForIngest = is_array($msgResult['chat'] ?? null)
                ? array_merge($chatMeta, $msgResult['chat'])
                : $chatMeta;

            $this->inbox->ingestMessages($branchSlug, $chatForIngest, $messages);
            $messagesIngested += count($messages);
        }

        $incomingAfter = $this->countIncomingMessages();

        return [
            'ok' => true,
            'branch' => $branchSlug,
            'chats_total' => count($chatsResult['chats'] ?? []),
            'chats_matched' => count($matched),
            'chats_synced' => count($matched),
            'messages_ingested' => $messagesIngested,
            'incoming_total' => $incomingAfter,
            'incoming_new' => max(0, $incomingAfter - $incomingBefore),
        ];
    }

    /** @return array<string, true> */
    private function fetchActiveListingIds(): array
    {
        $ids = [];
        $page = 1;

        while ($page <= 50) {
            $res = $this->api->listItems('active', 100, $page);
            if (! ($res['ok'] ?? false)) {
                break;
            }
            $batch = $res['resources'] ?? [];
            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $row) {
                if (! is_array($row) || ! isset($row['id'])) {
                    continue;
                }
                $ids[(string) $row['id']] = true;
            }

            if (count($batch) < 100) {
                break;
            }
            $page++;
        }

        return $ids;
    }

    /**
     * @return array<string, array{slug: string, label: string, user_id: string}>
     */
    private function branchesToSync(?string $branchSlug): array
    {
        $out = [];
        foreach (AvitoBranchConfig::branches() as $slug => $branch) {
            if (empty($branch['user_id'])) {
                continue;
            }
            if ($branchSlug !== null && $branchSlug !== '' && $slug !== $branchSlug) {
                continue;
            }
            $out[$slug] = [
                'slug' => $slug,
                'label' => (string) $branch['label'],
                'user_id' => (string) $branch['user_id'],
            ];
        }

        return $out;
    }

    private function countIncomingMessages(): int
    {
        return (int) AvitoMessage::query()->where('direction', 'in')->count();
    }
}
