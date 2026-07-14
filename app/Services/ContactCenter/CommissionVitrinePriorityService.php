<?php

namespace App\Services\ContactCenter;

use App\Models\CommissionContract;
use App\Models\ContactCenterLead;
use App\Models\Item;
use App\Models\ItemReservation;
use App\Models\ItemStatus;
use App\Models\PurchaseContract;
use App\Services\Items\ItemAvitoLifeService;
use Carbon\Carbon;

/** Приоритетный список комиссионных товаров на витрине для колл-центра. */
class CommissionVitrinePriorityService
{
    public const STALE_DAYS_THRESHOLD = 60;

    public function __construct(
        private ItemAvitoLifeService $avitoLife = new ItemAvitoLifeService(),
    ) {}

    /** @return list<int> */
    public function vitrineStatusIds(): array
    {
        // По текущему правилу: отбираем строго по статусу "Товар".
        return ItemStatus::query()
            ->where('name', 'Товар')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $storeIds
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, int|float>, stale_days: int}
     */
    public function buildList(array $storeIds, array $filters = []): array
    {
        $statusIds = $this->vitrineStatusIds();
        $staleOnly = ! empty($filters['stale_only']);
        $storeFilter = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $search = trim((string) ($filters['search'] ?? ''));

        // Витрина в данных бывает двух типов:
        // - комиссия: CommissionContract (is_sold = false)
        // - скупка/витрина: PurchaseContract, проданность определяется по наличию SaleContract у товара
        $itemsQuery = Item::query()
            ->with([
                'status',
                'category',
                'brand',
                'store',
                'storageLocation',
                'commissionContract.client',
                'purchaseContract.client',
                'saleContract',
            ])
            ->whereIn('store_id', $storeIds)
            ->when($statusIds !== [], fn ($q) => $q->whereIn('status_id', $statusIds))
            ->where(function ($sq) {
                $sq->whereHas('commissionContract', function ($cq) {
                    $cq->where('is_sold', false);
                })->orWhere(function ($pq) {
                    $pq->whereHas('purchaseContract')
                        ->whereDoesntHave('saleContract');
                });
            });

        if ($storeFilter) {
            $itemsQuery->where('store_id', $storeFilter);
        }

        if ($search !== '') {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('commissionContract', function ($cq) use ($search) {
                        $cq->where('contract_number', 'like', "%{$search}%")
                            ->orWhereHas('client', function ($clq) use ($search) {
                                $clq->where('full_name', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('purchaseContract', function ($pq) use ($search) {
                        $pq->where('contract_number', 'like', "%{$search}%")
                            ->orWhereHas('client', function ($clq) use ($search) {
                                $clq->where('full_name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $items = $itemsQuery->get();
        $itemIds = $items->pluck('id')->unique()->values()->all();

        $leadsCount = $this->countLeadsByItem($itemIds);
        $reservationsCount = $this->countReservationsByItem($itemIds);
        $activeReservations = $this->activeReservationItemIds($itemIds);
        $avitoInquiriesCount = $this->avitoLife->inquiryCountsForItems($items);

        $rows = [];
        foreach ($items as $item) {
            $row = $this->mapRow(
                $item,
                (int) ($leadsCount[$item->id] ?? 0),
                (int) ($reservationsCount[$item->id] ?? 0),
                isset($activeReservations[$item->id]),
                (int) ($avitoInquiriesCount[$item->id] ?? 0),
            );

            if ($staleOnly && ! $row['is_stale']) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => $b['priority_score'] <=> $a['priority_score']);

        $staleCount = count(array_filter($rows, fn (array $r) => $r['is_stale']));
        $lowInterestCount = count(array_filter($rows, fn (array $r) => $r['is_stale'] && $r['interest_count'] === 0));

        return [
            'rows' => $rows,
            'totals' => [
                'count' => count($rows),
                'stale_count' => $staleCount,
                'low_interest_stale_count' => $lowInterestCount,
                'amount' => round(array_sum(array_column($rows, 'current_price')), 2),
            ],
            'stale_days' => self::STALE_DAYS_THRESHOLD,
        ];
    }

    /** @param  list<int>  $itemIds  @return array<int, int> */
    private function countLeadsByItem(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return ContactCenterLead::query()
            ->whereIn('item_id', $itemIds)
            ->whereNotIn('status', [
                ContactCenterLead::STATUS_SPAM,
                ContactCenterLead::STATUS_CLOSED_LOST,
            ])
            ->selectRaw('item_id, COUNT(*) as cnt')
            ->groupBy('item_id')
            ->pluck('cnt', 'item_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @param  list<int>  $itemIds  @return array<int, int> */
    private function countReservationsByItem(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return ItemReservation::query()
            ->whereIn('item_id', $itemIds)
            ->selectRaw('item_id, COUNT(*) as cnt')
            ->groupBy('item_id')
            ->pluck('cnt', 'item_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @param  list<int>  $itemIds  @return array<int, true> */
    private function activeReservationItemIds(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return ItemReservation::query()
            ->whereIn('item_id', $itemIds)
            ->where('status', ItemReservation::STATUS_ACTIVE)
            ->where('reserved_until', '>', now())
            ->pluck('item_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapRow(
        Item $item,
        int $leadsCount,
        int $reservationsCount,
        bool $hasActiveReservation,
        int $avitoInquiriesCount,
    ): array {
        $commission = $item->commissionContract;
        $purchase = $item->purchaseContract;
        $listedAt = $commission?->created_at
            ? Carbon::parse($commission->created_at)
            : ($purchase?->purchase_date ? Carbon::parse($purchase->purchase_date) : Carbon::parse($item->created_at));
        $daysOnVitrine = (int) $listedAt->diffInDays(now());
        $isStale = $daysOnVitrine >= self::STALE_DAYS_THRESHOLD;

        $contractNumber = $commission?->contract_number ?? $purchase?->contract_number;
        $contractKind = $commission ? 'commission' : ($purchase ? 'purchase' : null);
        $contractLabel = $commission ? 'Комиссия' : ($purchase ? 'Скупка' : null);
        $clientName = $commission?->client?->full_name ?? $purchase?->client?->full_name;

        $fallbackPrice = $commission?->client_price ?? $purchase?->purchase_amount ?? 0;
        $currentPrice = (float) ($item->current_price ?? $fallbackPrice ?? 0);
        $marketPrice = (float) ($item->initial_price ?? $fallbackPrice ?? $currentPrice);
        $priceVsMarket = $marketPrice > 0
            ? round((($currentPrice / $marketPrice) - 1) * 100, 1)
            : null;

        $interestCount = $leadsCount + $reservationsCount + $avitoInquiriesCount;

        return [
            'item_id' => $item->id,
            'contract_id' => $commission?->id ?? $purchase?->id,
            'contract_number' => $contractNumber,
            'contract_kind' => $contractKind,
            'contract_label' => $contractLabel,
            'barcode' => $item->barcode,
            'name' => $item->name,
            'store_name' => $item->store?->name ?? '—',
            'store_id' => $item->store_id,
            'category_name' => $item->category?->name,
            'client_name' => $clientName,
            'status_name' => $item->status?->name,
            'days_on_vitrine' => $daysOnVitrine,
            'is_stale' => $isStale,
            'current_price' => $currentPrice,
            'market_price' => $marketPrice > 0 ? $marketPrice : null,
            'price_vs_market_pct' => $priceVsMarket,
            'leads_count' => $leadsCount,
            'reservations_count' => $reservationsCount,
            'avito_inquiries_count' => $avitoInquiriesCount,
            'interest_count' => $interestCount,
            'has_active_reservation' => $hasActiveReservation,
            'priority_score' => $this->priorityScore($daysOnVitrine, $isStale, $interestCount, $priceVsMarket, $currentPrice, $hasActiveReservation),
            'listed_at' => $listedAt->format('d.m.Y'),
            'photo_url' => $this->firstPhotoUrl($item),
        ];
    }

    private function priorityScore(
        int $daysOnVitrine,
        bool $isStale,
        int $interestCount,
        ?float $priceVsMarket,
        float $currentPrice,
        bool $hasActiveReservation,
    ): int {
        $score = 0;

        if ($isStale) {
            $score += 40 + min(30, (int) floor(($daysOnVitrine - self::STALE_DAYS_THRESHOLD) / 10));
        } else {
            $score += min(20, (int) floor($daysOnVitrine / 5));
        }

        if ($interestCount === 0) {
            $score += 25;
        } elseif ($interestCount === 1) {
            $score += 10;
        }

        if ($priceVsMarket !== null && $priceVsMarket > 0) {
            $score += min(20, (int) floor($priceVsMarket / 5));
        }

        $score += min(15, (int) floor($currentPrice / 10000));

        if ($hasActiveReservation) {
            $score -= 20;
        }

        return max(0, $score);
    }

    private function firstPhotoUrl(Item $item): ?string
    {
        $photos = $item->photos;
        if ($photos === []) {
            return null;
        }

        $path = $photos[0];
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.$path);
    }

    public function applyDiscount(Item $item, float $newPrice, ?string $reason, int $userId): void
    {
        $oldPrice = $item->current_price !== null ? (float) $item->current_price : null;

        $item->update(['current_price' => $newPrice]);

        if ($item->commissionContract && ! $item->commissionContract->is_sold) {
            $item->commissionContract->update(['client_price' => $newPrice]);
        }

        $item->priceAdjustments()->create([
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'reason' => $reason,
            'created_by' => $userId,
        ]);
    }
}
