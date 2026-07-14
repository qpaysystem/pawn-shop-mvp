<?php

namespace App\Services\Items;

use App\Models\AvitoChat;
use App\Models\Item;
use App\Services\Avito\AvitoApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/** Avito-объявление и обращения для карты жизни товара. */
class ItemAvitoLifeService
{
    private const MATCH_THRESHOLD = 70;

    public function __construct(
        private AvitoApiService $avitoApi = new AvitoApiService(),
    ) {}

    /**
     * Входящие сообщения Avito по списку товаров портала (пакетно, для витрины).
     *
     * @param  iterable<int, Item>  $items
     * @return array<int, int>  item_id => count
     */
    public function inquiryCountsForItems(iterable $items): array
    {
        $items = collect($items)->values();
        if ($items->isEmpty()) {
            return [];
        }

        $chats = AvitoChat::query()
            ->withCount(['messages as incoming_count' => fn ($q) => $q->where('direction', 'in')])
            ->get();

        $byListingId = [];
        foreach ($chats as $chat) {
            $cnt = (int) ($chat->incoming_count ?? 0);
            if ($cnt === 0 || ! $chat->item_id) {
                continue;
            }
            $byListingId[(string) $chat->item_id] = ($byListingId[(string) $chat->item_id] ?? 0) + $cnt;
        }

        $ads = $this->cachedActiveAds();
        $counts = [];

        foreach ($items as $item) {
            $itemId = (int) $item->id;
            $listingId = $this->matchListingIdForName((string) $item->name, $ads);

            if ($listingId !== null && isset($byListingId[$listingId])) {
                $counts[$itemId] = $byListingId[$listingId];

                continue;
            }

            $itemNorm = $this->norm((string) $item->name);
            $total = 0;
            foreach ($chats as $chat) {
                $cnt = (int) ($chat->incoming_count ?? 0);
                if ($cnt === 0) {
                    continue;
                }
                if ($listingId !== null && (string) $chat->item_id === $listingId) {
                    continue;
                }
                $title = $this->norm((string) ($chat->item_title ?? ''));
                if ($title !== '' && $this->similarityScore($itemNorm, $title) >= self::MATCH_THRESHOLD) {
                    $total += $cnt;
                }
            }
            if ($total > 0) {
                $counts[$itemId] = $total;
            }
        }

        return $counts;
    }

    /**
     * @return array{
     *   listing: ?array{id: ?string, title: string, url: ?string, price: ?string, status: ?string, match_score: ?int},
     *   chats_count: int,
     *   inquiries_total: int,
     *   inquiries_30d: int,
     *   last_inquiry_at: ?Carbon
     * }
     */
    public function summaryForItem(Item $item): array
    {
        $listing = $this->resolveActiveListing($item);
        $chats = $this->chatsForItem($item, $listing['id'] ?? null);

        $inquiriesTotal = 0;
        $inquiries30d = 0;
        $lastInquiryAt = null;
        $since30d = now()->subDays(30);

        foreach ($chats as $chat) {
            foreach ($chat->messages as $msg) {
                if ($msg->direction !== 'in') {
                    continue;
                }
                $inquiriesTotal++;
                $at = $msg->sent_at ?? $msg->created_at;
                if ($at && $at->gte($since30d)) {
                    $inquiries30d++;
                }
                if ($at && ($lastInquiryAt === null || $at->gt($lastInquiryAt))) {
                    $lastInquiryAt = $at;
                }
            }
        }

        if ($listing === null && $chats->isNotEmpty()) {
            $first = $chats->first();
            $listing = [
                'id' => $first->item_id,
                'title' => (string) ($first->item_title ?? $item->name),
                'url' => $first->item_url,
                'price' => $first->item_price,
                'status' => 'из чатов',
                'match_score' => self::MATCH_THRESHOLD,
            ];
        }

        return [
            'listing' => $listing,
            'chats_count' => $chats->count(),
            'inquiries_total' => $inquiriesTotal,
            'inquiries_30d' => $inquiries30d,
            'last_inquiry_at' => $lastInquiryAt,
        ];
    }

    /**
     * @return array<int, array{at: Carbon, kind: string, title: string, meta: ?string, url: ?string}>
     */
    public function lifeMapEventsForItem(Item $item): array
    {
        $listing = $this->resolveActiveListing($item);
        $chats = $this->chatsForItem($item, $listing['id'] ?? null);
        $events = [];

        foreach ($chats as $chat) {
            foreach ($chat->messages as $msg) {
                if ($msg->direction !== 'in') {
                    continue;
                }
                $who = trim((string) ($chat->peer_name ?? 'Покупатель'));
                $text = trim((string) ($msg->text ?? ''));
                if ($text === '') {
                    $text = '[сообщение без текста]';
                }

                $events[] = [
                    'at' => Carbon::parse($msg->sent_at ?? $msg->created_at),
                    'kind' => 'avito_contact',
                    'title' => 'Обращение Avito от '.$who,
                    'meta' => mb_substr($text, 0, 240),
                    'url' => $msg->call_center_contact_id
                        ? route('call-center.show', $msg->call_center_contact_id)
                        : null,
                ];
            }
        }

        return $events;
    }

    /**
     * @return ?array{id: ?string, title: string, url: ?string, price: ?string, status: ?string, match_score: int}
     */
    private function resolveActiveListing(Item $item): ?array
    {
        $match = $this->matchListingForName((string) $item->name, $this->cachedActiveAds());
        if ($match === null) {
            return null;
        }

        $best = $match['ad'];
        $bestScore = $match['score'];

        $price = null;
        if (isset($best['price']) && is_numeric($best['price'])) {
            $price = number_format((float) $best['price'], 0, '', ' ').' ₽';
        }

        return [
            'id' => isset($best['id']) ? (string) $best['id'] : null,
            'title' => (string) $best['title'],
            'url' => isset($best['url']) ? (string) $best['url'] : null,
            'price' => $price,
            'status' => isset($best['status']) ? (string) $best['status'] : 'active',
            'match_score' => $bestScore,
        ];
    }

    /**
     * @return list<array{title: string, id?: string, url?: string, price?: float, status?: string}>
     */
    private function cachedActiveAds(): array
    {
        return Cache::remember('avito_active_items_for_life_map', 600, function () {
            $ads = [];
            $page = 1;

            while ($page <= 20) {
                $res = $this->avitoApi->listItems('active', 100, $page);
                if (! ($res['ok'] ?? false)) {
                    break;
                }
                $batch = $res['resources'] ?? [];
                if (! is_array($batch) || $batch === []) {
                    break;
                }

                foreach ($batch as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $title = trim((string) ($row['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $ad = ['title' => $title];
                    if (isset($row['id'])) {
                        $ad['id'] = (string) $row['id'];
                    }
                    if (isset($row['url'])) {
                        $ad['url'] = (string) $row['url'];
                    }
                    if (isset($row['status'])) {
                        $ad['status'] = (string) $row['status'];
                    }
                    if (isset($row['price']) && is_numeric($row['price'])) {
                        $ad['price'] = (float) $row['price'];
                    }
                    $ads[] = $ad;
                }

                $page++;
            }

            return $ads;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, AvitoChat>
     */
    private function chatsForItem(Item $item, ?string $listingId)
    {
        $itemNorm = $this->norm((string) $item->name);

        $query = AvitoChat::query()
            ->with(['messages' => function ($q) {
                $q->where('direction', 'in')->orderBy('sent_at')->orderBy('id');
            }]);

        if ($listingId !== null && $listingId !== '') {
            $query->where('item_id', $listingId);
        } else {
            $query->whereNotNull('item_title');
        }

        $chats = $query->get();

        if ($listingId !== null && $listingId !== '') {
            return $chats;
        }

        return $chats->filter(function (AvitoChat $chat) use ($itemNorm) {
            $title = $this->norm((string) ($chat->item_title ?? ''));

            return $title !== '' && $this->similarityScore($itemNorm, $title) >= self::MATCH_THRESHOLD;
        })->values();
    }

    /**
     * @param  list<array{title: string, id?: string, url?: string, price?: float, status?: string}>  $ads
     * @return ?array{ad: array{title: string, id?: string, url?: string, price?: float, status?: string}, score: int}
     */
    private function matchListingForName(string $name, array $ads): ?array
    {
        $itemNorm = $this->norm($name);
        if ($itemNorm === '') {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($ads as $ad) {
            $title = trim((string) ($ad['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $score = $this->similarityScore($itemNorm, $this->norm($title));
            if ($score >= self::MATCH_THRESHOLD && $score > $bestScore) {
                $bestScore = $score;
                $best = $ad;
            }
        }

        if ($best === null) {
            return null;
        }

        return ['ad' => $best, 'score' => $bestScore];
    }

    /**
     * @param  list<array{title: string, id?: string}>  $ads
     */
    private function matchListingIdForName(string $name, array $ads): ?string
    {
        $match = $this->matchListingForName($name, $ads);
        if ($match === null) {
            return null;
        }
        $id = $match['ad']['id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';

        return trim($s);
    }

    private function similarityScore(string $a, string $b): int
    {
        if ($a === '' || $b === '') {
            return 0;
        }
        if (abs(mb_strlen($a) - mb_strlen($b)) > 80) {
            return 0;
        }
        $pct = 0.0;
        similar_text($a, $b, $pct);

        return (int) round($pct);
    }
}
