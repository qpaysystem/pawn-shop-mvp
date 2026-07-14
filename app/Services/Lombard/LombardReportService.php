<?php

namespace App\Services\Lombard;

use App\Models\Item;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\SaleContract;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Операционные отчёты ломбарда: залоги, выкупы, прибыль, остатки. */
class LombardReportService
{
    /**
     * @param  list<int>  $storeIds
     * @return array{
     *   date_from: string,
     *   date_to: string,
     *   totals: array<string, int|float>,
     *   by_store: list<array<string, mixed>>
     * }
     */
    public function pawnsAndRedemptions(array $storeIds, ?int $filterStoreId, Carbon $from, Carbon $to): array
    {
        $ids = $this->scopedStoreIds($storeIds, $filterStoreId);
        $stores = Store::whereIn('id', $ids)->orderBy('name')->get();

        $issuedQuery = PawnContract::query()
            ->whereIn('store_id', $ids)
            ->whereDate('loan_date', '>=', $from->toDateString())
            ->whereDate('loan_date', '<=', $to->toDateString());

        $redeemedQuery = PawnContract::query()
            ->whereIn('store_id', $ids)
            ->where('is_redeemed', true)
            ->whereNotNull('redeemed_at')
            ->whereDate('redeemed_at', '>=', $from->toDateString())
            ->whereDate('redeemed_at', '<=', $to->toDateString());

        $activeNow = PawnContract::query()
            ->whereIn('store_id', $ids)
            ->where('is_redeemed', false)
            ->count();

        $byStore = [];
        foreach ($stores as $store) {
            $issued = (clone $issuedQuery)->where('store_id', $store->id);
            $redeemed = (clone $redeemedQuery)->where('store_id', $store->id);
            $byStore[] = [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'issued_count' => (clone $issued)->count(),
                'issued_amount' => (float) (clone $issued)->sum('loan_amount'),
                'redeemed_count' => (clone $redeemed)->count(),
                'redeemed_amount' => (float) (clone $redeemed)->sum('buyback_amount'),
                'active_count' => PawnContract::where('store_id', $store->id)->where('is_redeemed', false)->count(),
            ];
        }

        return [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'totals' => [
                'issued_count' => (clone $issuedQuery)->count(),
                'issued_amount' => (float) (clone $issuedQuery)->sum('loan_amount'),
                'redeemed_count' => (clone $redeemedQuery)->count(),
                'redeemed_amount' => (float) (clone $redeemedQuery)->sum('buyback_amount'),
                'active_count' => $activeNow,
            ],
            'by_store' => $byStore,
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @return array{
     *   date_from: string,
     *   date_to: string,
     *   totals: array<string, float|int>,
     *   by_store: list<array<string, mixed>>,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function pawnProfit(array $storeIds, ?int $filterStoreId, Carbon $from, Carbon $to, int $rowLimit = 100): array
    {
        $ids = $this->scopedStoreIds($storeIds, $filterStoreId);
        $stores = Store::whereIn('id', $ids)->orderBy('name')->get();

        $contracts = PawnContract::with(['client', 'item', 'store'])
            ->whereIn('store_id', $ids)
            ->where('is_redeemed', true)
            ->whereNotNull('redeemed_at')
            ->whereDate('redeemed_at', '>=', $from->toDateString())
            ->whereDate('redeemed_at', '<=', $to->toDateString())
            ->orderByDesc('redeemed_at')
            ->get();

        $rows = $contracts->take($rowLimit)->map(function (PawnContract $pawn) {
            $loan = (float) $pawn->loan_amount;
            $buyback = (float) ($pawn->buyback_amount ?: $pawn->redemption_amount);
            $profit = round(max(0, $buyback - $loan), 2);

            return [
                'contract_number' => $pawn->contract_number,
                'client_name' => $pawn->client?->full_name ?? '—',
                'item_name' => $pawn->item?->name ?? '—',
                'store_name' => $pawn->store?->name ?? '—',
                'loan_amount' => $loan,
                'buyback_amount' => $buyback,
                'profit' => $profit,
                'redeemed_at' => $pawn->redeemed_at?->format('d.m.Y'),
            ];
        })->values()->all();

        $totalProfit = $contracts->sum(fn (PawnContract $p) => max(0, (float) ($p->buyback_amount ?: $p->redemption_amount) - (float) $p->loan_amount));
        $totalLoan = (float) $contracts->sum('loan_amount');
        $totalBuyback = (float) $contracts->sum(fn (PawnContract $p) => (float) ($p->buyback_amount ?: $p->redemption_amount));

        $byStore = [];
        foreach ($stores as $store) {
            $storeContracts = $contracts->where('store_id', $store->id);
            $profit = $storeContracts->sum(fn (PawnContract $p) => max(0, (float) ($p->buyback_amount ?: $p->redemption_amount) - (float) $p->loan_amount));
            $byStore[] = [
                'store_name' => $store->name,
                'count' => $storeContracts->count(),
                'loan_amount' => (float) $storeContracts->sum('loan_amount'),
                'buyback_amount' => (float) $storeContracts->sum(fn (PawnContract $p) => (float) ($p->buyback_amount ?: $p->redemption_amount)),
                'profit' => round($profit, 2),
            ];
        }

        return [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'totals' => [
                'count' => $contracts->count(),
                'loan_amount' => $totalLoan,
                'buyback_amount' => $totalBuyback,
                'profit' => round($totalProfit, 2),
            ],
            'by_store' => $byStore,
            'rows' => $rows,
            'rows_total' => $contracts->count(),
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @return array{
     *   date_from: string,
     *   date_to: string,
     *   totals: array<string, float|int>,
     *   by_store: list<array<string, mixed>>,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function salesProfit(array $storeIds, ?int $filterStoreId, Carbon $from, Carbon $to, int $rowLimit = 100): array
    {
        $ids = $this->scopedStoreIds($storeIds, $filterStoreId);
        $stores = Store::whereIn('id', $ids)->orderBy('name')->get();

        $sales = SaleContract::with(['client', 'item.purchaseContract', 'store'])
            ->whereIn('store_id', $ids)
            ->whereDate('sale_date', '>=', $from->toDateString())
            ->whereDate('sale_date', '<=', $to->toDateString())
            ->orderByDesc('sale_date')
            ->get();

        $rows = $sales->take($rowLimit)->map(function (SaleContract $sale) {
            $revenue = (float) $sale->sale_amount;
            $cost = $this->itemCost($sale->item);
            $profit = round($revenue - $cost, 2);

            return [
                'contract_number' => $sale->contract_number,
                'client_name' => $sale->client?->full_name ?? '—',
                'item_name' => $sale->item?->name ?? '—',
                'store_name' => $sale->store?->name ?? '—',
                'cost' => $cost,
                'revenue' => $revenue,
                'profit' => $profit,
                'sale_date' => $sale->sale_date?->format('d.m.Y'),
            ];
        })->values()->all();

        $totalRevenue = (float) $sales->sum('sale_amount');
        $totalCost = (float) $sales->sum(fn (SaleContract $s) => $this->itemCost($s->item));
        $totalProfit = round($totalRevenue - $totalCost, 2);

        $byStore = [];
        foreach ($stores as $store) {
            $storeSales = $sales->where('store_id', $store->id);
            $revenue = (float) $storeSales->sum('sale_amount');
            $cost = (float) $storeSales->sum(fn (SaleContract $s) => $this->itemCost($s->item));
            $byStore[] = [
                'store_name' => $store->name,
                'count' => $storeSales->count(),
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
            ];
        }

        return [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'totals' => [
                'count' => $sales->count(),
                'revenue' => $totalRevenue,
                'cost' => $totalCost,
                'profit' => $totalProfit,
            ],
            'by_store' => $byStore,
            'rows' => $rows,
            'rows_total' => $sales->count(),
        ];
    }

    /**
     * Валовая прибыль как в 1С: залоги + товары + комиссионка.
     *
     * @param  list<int>  $storeIds
     * @return array{
     *   date_from: string,
     *   date_to: string,
     *   totals: array<string, float|int>,
     *   by_category: list<array<string, mixed>>,
     *   by_store: list<array<string, mixed>>
     * }
     */
    public function grossProfit(array $storeIds, ?int $filterStoreId, Carbon $from, Carbon $to): array
    {
        $pawn = $this->pawnProfit($storeIds, $filterStoreId, $from, $to, 10_000);
        $sales = $this->salesProfit($storeIds, $filterStoreId, $from, $to, 10_000);

        $byCategory = [
            [
                'category' => 'Залоги',
                'count' => $pawn['totals']['count'],
                'cost' => $pawn['totals']['loan_amount'],
                'revenue' => $pawn['totals']['buyback_amount'],
                'profit' => $pawn['totals']['profit'],
            ],
            [
                'category' => 'Товары',
                'count' => $sales['totals']['count'],
                'cost' => $sales['totals']['cost'],
                'revenue' => $sales['totals']['revenue'],
                'profit' => $sales['totals']['profit'],
            ],
        ];

        $storeMap = [];
        foreach ($pawn['by_store'] as $row) {
            $name = (string) $row['store_name'];
            $storeMap[$name] = [
                'store_name' => $name,
                'count' => (int) $row['count'],
                'cost' => (float) $row['loan_amount'],
                'revenue' => (float) $row['buyback_amount'],
                'profit' => (float) $row['profit'],
            ];
        }
        foreach ($sales['by_store'] as $row) {
            $name = (string) $row['store_name'];
            if (! isset($storeMap[$name])) {
                $storeMap[$name] = [
                    'store_name' => $name,
                    'count' => 0,
                    'cost' => 0.0,
                    'revenue' => 0.0,
                    'profit' => 0.0,
                ];
            }
            $storeMap[$name]['count'] += (int) $row['count'];
            $storeMap[$name]['cost'] += (float) $row['cost'];
            $storeMap[$name]['revenue'] += (float) $row['revenue'];
            $storeMap[$name]['profit'] += (float) $row['profit'];
        }

        $totalProfit = $pawn['totals']['profit'] + $sales['totals']['profit'];

        return [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'totals' => [
                'count' => $pawn['totals']['count'] + $sales['totals']['count'],
                'cost' => $pawn['totals']['loan_amount'] + $sales['totals']['cost'],
                'revenue' => $pawn['totals']['buyback_amount'] + $sales['totals']['revenue'],
                'profit' => round($totalProfit, 2),
                'pawn_profit' => $pawn['totals']['profit'],
                'sales_profit' => $sales['totals']['profit'],
            ],
            'by_category' => $byCategory,
            'by_store' => array_values($storeMap),
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @return array{
     *   date_from: ?string,
     *   date_to: ?string,
     *   totals: array<string, int|float>,
     *   by_store: list<array<string, mixed>>,
     *   by_item_kind: list<array<string, mixed>>,
     *   rows: list<array<string, mixed>>,
     *   rows_total: int,
     *   truncated: bool
     * }
     */
    public function inventoryRegister(
        array $storeIds,
        ?int $filterStoreId,
        ?Carbon $from,
        ?Carbon $to,
        string $stockKind = 'all',
        string $itemKind = 'all',
        ?int $categoryId = null,
        string $stockStatus = 'in_stock',
        int $rowLimit = 1000,
    ): array {
        $ids = $this->scopedStoreIds($storeIds, $filterStoreId);
        $rows = [];

        if (in_array($stockKind, ['all', 'pawn'], true)) {
            $pawnQuery = PawnContract::with(['client', 'item.category', 'store'])
                ->whereIn('store_id', $ids);

            if ($stockStatus === 'in_stock') {
                $pawnQuery->where('is_redeemed', false);
            } elseif ($stockStatus === 'redeemed') {
                $pawnQuery->where('is_redeemed', true);
            }

            if ($from) {
                $pawnQuery->whereDate('loan_date', '>=', $from->toDateString());
            }
            if ($to) {
                $pawnQuery->whereDate('loan_date', '<=', $to->toDateString());
            }

            foreach ($pawnQuery->orderBy('loan_date')->cursor() as $pawn) {
                $row = $this->mapPawnInventoryRow($pawn);
                if ($row && $this->inventoryRowMatches($row, $itemKind, $categoryId)) {
                    $rows[] = $row;
                }
            }
        }

        if (in_array($stockKind, ['all', 'purchase'], true)) {
            $purchaseQuery = PurchaseContract::with(['client', 'item.category', 'item.saleContract', 'store'])
                ->whereIn('store_id', $ids);

            if ($stockStatus === 'in_stock') {
                $purchaseQuery->whereDoesntHave('item.saleContract');
            } elseif ($stockStatus === 'sold') {
                $purchaseQuery->whereHas('item.saleContract');
            }

            if ($from) {
                $purchaseQuery->whereDate('purchase_date', '>=', $from->toDateString());
            }
            if ($to) {
                $purchaseQuery->whereDate('purchase_date', '<=', $to->toDateString());
            }

            foreach ($purchaseQuery->orderBy('purchase_date')->cursor() as $purchase) {
                $row = $this->mapPurchaseInventoryRow($purchase);
                if ($row && $this->inventoryRowMatches($row, $itemKind, $categoryId)) {
                    $rows[] = $row;
                }
            }
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $b['receipt_date_sort'], (string) $a['receipt_date_sort']));

        $rowsTotal = count($rows);
        $totals = [
            'count' => $rowsTotal,
            'amount' => round(array_sum(array_map(fn (array $r) => (float) $r['amount'], $rows)), 2),
            'pawn_count' => count(array_filter($rows, fn (array $r) => $r['stock_kind'] === 'pawn')),
            'purchase_count' => count(array_filter($rows, fn (array $r) => $r['stock_kind'] === 'purchase')),
            'overdue_count' => count(array_filter($rows, fn (array $r) => ($r['status_code'] ?? '') === 'overdue')),
        ];

        $byStore = [];
        $byItemKind = [];
        foreach ($rows as $row) {
            $storeName = (string) $row['store_name'];
            $kindKey = (string) $row['item_kind'];
            if (! isset($byStore[$storeName])) {
                $byStore[$storeName] = ['store_name' => $storeName, 'count' => 0, 'amount' => 0.0];
            }
            $byStore[$storeName]['count']++;
            $byStore[$storeName]['amount'] += (float) $row['amount'];

            if (! isset($byItemKind[$kindKey])) {
                $byItemKind[$kindKey] = [
                    'item_kind' => $kindKey,
                    'item_kind_label' => (string) $row['item_kind_label'],
                    'count' => 0,
                    'amount' => 0.0,
                ];
            }
            $byItemKind[$kindKey]['count']++;
            $byItemKind[$kindKey]['amount'] += (float) $row['amount'];
        }

        $truncated = $rowsTotal > $rowLimit;
        if ($truncated) {
            $rows = array_slice($rows, 0, $rowLimit);
        }

        return [
            'date_from' => $from?->format('Y-m-d'),
            'date_to' => $to?->format('Y-m-d'),
            'totals' => $totals,
            'by_store' => array_values($byStore),
            'by_item_kind' => array_values($byItemKind),
            'rows' => $rows,
            'rows_total' => $rowsTotal,
            'truncated' => $truncated,
        ];
    }

    /** @return array<string, string> */
    public function itemKindOptions(): array
    {
        return [
            'all' => 'Все виды',
            'jewelry' => 'Ювелирные изделия',
            'tech' => 'Техника',
            'watches' => 'Часы',
            'fur' => 'Меха',
            'tools' => 'Инструмент',
            'other' => 'Прочее',
        ];
    }

    /** @return array<string, string> */
    public function stockKindOptions(): array
    {
        return [
            'all' => 'Залог и скупка',
            'pawn' => 'Только залог',
            'purchase' => 'Только скупка',
        ];
    }

    /** @return array<string, string> */
    public function stockStatusOptions(): array
    {
        return [
            'in_stock' => 'В остатке',
            'all' => 'Все статусы',
            'redeemed' => 'Выкупленные залоги',
            'sold' => 'Проданные (скупка)',
        ];
    }

    public function categoriesForFilter(): Collection
    {
        return \App\Models\ItemCategory::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function inventoryRowMatches(array $row, string $itemKind, ?int $categoryId): bool
    {
        if ($itemKind !== 'all' && ($row['item_kind'] ?? '') !== $itemKind) {
            return false;
        }
        if ($categoryId && (int) ($row['category_id'] ?? 0) !== $categoryId) {
            return false;
        }

        return true;
    }

    /** @return ?array<string, mixed> */
    private function mapPawnInventoryRow(PawnContract $pawn): ?array
    {
        $item = $pawn->item;
        if (! $item) {
            return null;
        }

        $itemName = (string) $item->name;
        $kind = $this->inferItemKind($itemName, $item);
        $receiptDate = $pawn->loan_date;
        $statusCode = $pawn->computed_status;

        return [
            'stock_kind' => 'pawn',
            'stock_kind_label' => 'Залог',
            'contract_number' => $pawn->contract_number,
            'store_name' => $pawn->store?->name ?? '—',
            'client_name' => $pawn->client?->full_name ?? '—',
            'item_name' => $itemName,
            'item_kind' => $kind,
            'item_kind_label' => $this->itemKindOptions()[$kind] ?? $kind,
            'category_id' => $item->category_id,
            'category_name' => $item->category?->name,
            'status' => match ($statusCode) {
                'overdue' => 'Просрочка',
                'redeemed' => 'Выкуплен',
                default => 'Действующий',
            },
            'status_code' => $statusCode,
            'receipt_date' => $receiptDate?->format('d.m.Y') ?? '—',
            'receipt_date_sort' => $receiptDate?->format('Y-m-d') ?? '',
            'expiry_date' => $pawn->expiry_date?->format('d.m.Y') ?? '—',
            'amount' => (float) $pawn->loan_amount,
            'days_in_stock' => $receiptDate ? $receiptDate->diffInDays(now()) : null,
        ];
    }

    /** @return ?array<string, mixed> */
    private function mapPurchaseInventoryRow(PurchaseContract $purchase): ?array
    {
        $item = $purchase->item;
        if (! $item) {
            return null;
        }

        $itemName = (string) $item->name;
        $kind = $this->inferItemKind($itemName, $item);
        $receiptDate = $purchase->purchase_date;
        $sold = $item->saleContract !== null;

        return [
            'stock_kind' => 'purchase',
            'stock_kind_label' => 'Скупка',
            'contract_number' => $purchase->contract_number,
            'store_name' => $purchase->store?->name ?? '—',
            'client_name' => $purchase->client?->full_name ?? '—',
            'item_name' => $itemName,
            'item_kind' => $kind,
            'item_kind_label' => $this->itemKindOptions()[$kind] ?? $kind,
            'category_id' => $item->category_id,
            'category_name' => $item->category?->name,
            'status' => $sold ? 'Продан' : 'На витрине',
            'status_code' => $sold ? 'sold' : 'on_display',
            'receipt_date' => $receiptDate?->format('d.m.Y') ?? '—',
            'receipt_date_sort' => $receiptDate?->format('Y-m-d') ?? '',
            'expiry_date' => '—',
            'amount' => (float) $purchase->purchase_amount,
            'days_in_stock' => $receiptDate ? $receiptDate->diffInDays(now()) : null,
        ];
    }

    public function inferItemKind(string $name, ?Item $item = null): string
    {
        if ($item?->category?->name) {
            $cat = mb_strtolower((string) $item->category->name);
            if (str_contains($cat, 'ювелир') || str_contains($cat, 'золот') || str_contains($cat, 'серебр')) {
                return 'jewelry';
            }
            if (str_contains($cat, 'техник') || str_contains($cat, 'телефон') || str_contains($cat, 'компьютер')) {
                return 'tech';
            }
            if (str_contains($cat, 'час')) {
                return 'watches';
            }
            if (str_contains($cat, 'мех')) {
                return 'fur';
            }
            if (str_contains($cat, 'инструмент')) {
                return 'tools';
            }
        }

        $n = mb_strtolower($name);
        if (preg_match('/золото|серебро|кольц|цеп|серьг|браслет|кулон|подвеск|монет|проба|ювелир|крест/ui', $n)) {
            return 'jewelry';
        }
        if (preg_match('/часы|watch|tissot|casio|fossil|romanson/ui', $n)) {
            return 'watches';
        }
        if (preg_match('/телефон|ноутбук|планшет|телевизор|iphone|samsung|xiaomi|huawei|компьютер|приставк|playstation|nintendo|моноблок|проектор/ui', $n)) {
            return 'tech';
        }
        if (preg_match('/собол|норк|мех|шуб|шапк/ui', $n)) {
            return 'fur';
        }
        if (preg_match('/дрель|шуруповерт|перфоратор|болгарк|лобзик|сварочн|станок|инструмент/ui', $n)) {
            return 'tools';
        }

        return 'other';
    }

    /**
     * @param  list<int>  $storeIds
     * @return array{
     *   totals: array<string, int|float>,
     *   by_store: list<array<string, mixed>>
     * }
     */
    public function inventorySummary(array $storeIds, ?int $filterStoreId): array
    {
        $ids = $this->scopedStoreIds($storeIds, $filterStoreId);
        $stores = Store::whereIn('id', $ids)->orderBy('name')->get();

        $pawnBase = PawnContract::query()->whereIn('store_id', $ids);
        $itemsBase = Item::query()->whereIn('store_id', $ids);
        $purchaseBase = PurchaseContract::query()->whereIn('store_id', $ids);
        $salesBase = SaleContract::query()->whereIn('store_id', $ids);

        $activePawn = (clone $pawnBase)->where('is_redeemed', false);
        $overduePawn = (clone $activePawn)->whereDate('expiry_date', '<', now()->toDateString());

        $byStore = [];
        foreach ($stores as $store) {
            $storePawns = PawnContract::where('store_id', $store->id);
            $active = (clone $storePawns)->where('is_redeemed', false);
            $byStore[] = [
                'store_name' => $store->name,
                'items_count' => Item::where('store_id', $store->id)->count(),
                'pawns_total' => (clone $storePawns)->count(),
                'pawns_active' => (clone $active)->count(),
                'pawns_redeemed' => (clone $storePawns)->where('is_redeemed', true)->count(),
                'pawns_overdue' => (clone $active)->whereDate('expiry_date', '<', now()->toDateString())->count(),
                'pawns_loan_amount' => (float) (clone $active)->sum('loan_amount'),
                'purchases_count' => PurchaseContract::where('store_id', $store->id)->count(),
                'sales_count' => SaleContract::where('store_id', $store->id)->count(),
            ];
        }

        return [
            'totals' => [
                'items_count' => (clone $itemsBase)->count(),
                'pawns_total' => (clone $pawnBase)->count(),
                'pawns_active' => (clone $activePawn)->count(),
                'pawns_redeemed' => (clone $pawnBase)->where('is_redeemed', true)->count(),
                'pawns_overdue' => (clone $overduePawn)->count(),
                'pawns_loan_amount' => (float) (clone $activePawn)->sum('loan_amount'),
                'purchases_count' => (clone $purchaseBase)->count(),
                'sales_count' => (clone $salesBase)->count(),
            ],
            'by_store' => $byStore,
        ];
    }

    /** @param  list<int>  $storeIds */
    public function storesForFilter(array $storeIds): Collection
    {
        return Store::whereIn('id', $storeIds)->orderBy('name')->get();
    }

    public function defaultDateFrom(): Carbon
    {
        return now()->subMonths(3)->startOfMonth();
    }

    public function defaultDateTo(): Carbon
    {
        return now();
    }

    private function itemCost(?Item $item): float
    {
        if (! $item) {
            return 0.0;
        }
        $purchase = $item->purchaseContract?->purchase_amount;
        if ($purchase !== null && (float) $purchase > 0) {
            return (float) $purchase;
        }

        return (float) ($item->initial_price ?? 0);
    }

    /** @param  list<int>  $storeIds
     * @return list<int>
     */
    private function scopedStoreIds(array $storeIds, ?int $filterStoreId): array
    {
        if ($filterStoreId && in_array($filterStoreId, $storeIds, true)) {
            return [$filterStoreId];
        }

        return $storeIds;
    }
}
