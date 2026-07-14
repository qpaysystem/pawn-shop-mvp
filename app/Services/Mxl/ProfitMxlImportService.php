<?php

namespace App\Services\Mxl;

use App\Models\Client;
use App\Models\Item;
use App\Models\ItemStatus;
use App\Models\ItemStatusHistory;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\SaleContract;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Догрузка выкупов и реализаций по отчёту валовой прибыли 1С. */
class ProfitMxlImportService
{
    /** @var array<int, true> */
    private array $redeemedPawnIds = [];

    /** @var array<int, true> */
    private array $soldItemIds = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $stats = [
        'parsed' => 0,
        'redeems_applied' => 0,
        'redeems_already' => 0,
        'redeems_not_found' => 0,
        'sales_created' => 0,
        'sales_already' => 0,
        'sales_not_found' => 0,
        'skipped' => 0,
    ];

    public function __construct(
        private MxlProfitParser $parser = new MxlProfitParser,
        private ReceiptsMxlImportService $stores = new ReceiptsMxlImportService,
        private InventoryPositionMatcher $matcher = new InventoryPositionMatcher,
    ) {}

    /**
     * @return array{success: bool, stats: array<string, int>, warnings: list<string>}
     */
    public function import(string $filePath, ?Carbon $from = null, ?Carbon $to = null, bool $dryRun = false): array
    {
        $this->reset();
        $rows = $this->parser->parse($filePath);
        $this->stats['parsed'] = count($rows);

        foreach ($rows as $row) {
            $date = $row['date'] instanceof Carbon ? $row['date'] : null;
            if ($from && $date && $date->lt($from->copy()->startOfDay())) {
                continue;
            }
            if ($to && $date && $date->gt($to->copy()->endOfDay())) {
                continue;
            }

            if ($dryRun) {
                $this->dryRunRow($row);

                continue;
            }

            try {
                DB::transaction(fn () => $this->importRow($row));
            } catch (\Throwable $e) {
                $this->warnings[] = mb_substr((string) ($row['item'] ?? '?'), 0, 40).': '.$e->getMessage();
            }
        }

        return [
            'success' => empty($this->warnings) || $this->stats['redeems_applied'] + $this->stats['sales_created'] > 0,
            'stats' => $this->stats,
            'warnings' => array_slice($this->warnings, 0, 50),
        ];
    }

    /** @param array<string, mixed> $row */
    private function dryRunRow(array $row): void
    {
        $category = (string) ($row['category'] ?? '');
        $store = $this->resolveStore((string) ($row['store'] ?? ''));
        if (! $store) {
            $this->stats['skipped']++;

            return;
        }

        if ($category === 'Залоги') {
            if ($this->findExistingRedeem($row, $store)) {
                $this->stats['redeems_already']++;

                return;
            }
            if ($this->findPawnForRow($row, $store)) {
                $this->stats['redeems_applied']++;
            } else {
                $this->stats['redeems_not_found']++;
            }

            return;
        }

        if ($this->categoryIsSale($category)) {
            if ($this->findExistingSale($row, $store)) {
                $this->stats['sales_already']++;

                return;
            }
            if ($this->findItemForSale($row, $store)) {
                $this->stats['sales_created']++;
            } else {
                $this->stats['sales_not_found']++;
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function importRow(array $row): void
    {
        $category = (string) ($row['category'] ?? '');
        $store = $this->resolveStore((string) ($row['store'] ?? ''));
        if (! $store) {
            $this->stats['skipped']++;

            return;
        }

        if ($category === 'Залоги') {
            $this->applyRedeem($row, $store);

            return;
        }

        if ($this->categoryIsSale($category)) {
            $this->applySale($row, $store);
        }
    }

    /** @param array<string, mixed> $row */
    private function applyRedeem(array $row, Store $store): void
    {
        if ($this->findExistingRedeem($row, $store)) {
            $this->stats['redeems_already']++;

            return;
        }

        $pawn = $this->findPawnForRow($row, $store);
        if (! $pawn) {
            $this->stats['redeems_not_found']++;
            $this->warnings[] = 'Выкуп не найден: '.mb_substr((string) $row['item'], 0, 50);

            return;
        }

        $date = $row['date'] instanceof Carbon ? $row['date'] : now();
        $revenue = (float) ($row['revenue'] ?? 0);

        $pawn->update([
            'buyback_amount' => $revenue > 0 ? $revenue : $pawn->buyback_amount,
            'is_redeemed' => true,
            'redeemed_at' => $date,
        ]);

        $this->redeemedPawnIds[$pawn->id] = true;
        $this->stats['redeems_applied']++;
    }

    /** @param array<string, mixed> $row */
    private function applySale(array $row, Store $store): void
    {
        if ($this->findExistingSale($row, $store)) {
            $this->stats['sales_already']++;

            return;
        }

        $item = $this->findItemForSale($row, $store);
        if (! $item) {
            $this->stats['sales_not_found']++;
            $this->warnings[] = 'Реализация — товар не найден: '.mb_substr((string) $row['item'], 0, 50);

            return;
        }

        $date = $row['date'] instanceof Carbon ? $row['date'] : now();
        $revenue = (float) ($row['revenue'] ?? 0);
        $purchase = $item->purchaseContract;
        $contractNumber = $purchase?->contract_number ?: ('ПР-'.$item->id);

        $sale = SaleContract::create([
            'contract_number' => $this->nextSaleNumber($contractNumber),
            'client_id' => $purchase?->client_id ?? $this->fallbackClientId(),
            'item_id' => $item->id,
            'store_id' => $store->id,
            'sale_amount' => $revenue,
            'sale_date' => $date,
            'lmb_data' => ['source' => 'profit_mxl'],
        ]);

        $statusSoldId = ItemStatus::where('name', 'Продан')->value('id');
        if ($statusSoldId) {
            $oldStatus = $item->status_id;
            $item->update(['status_id' => $statusSoldId, 'current_price' => $revenue]);
            ItemStatusHistory::create([
                'item_id' => $item->id,
                'old_status_id' => $oldStatus,
                'new_status_id' => $statusSoldId,
                'comment' => 'Реализация по отчёту прибыли 1С №'.$sale->contract_number,
            ]);
        }

        $this->soldItemIds[$item->id] = true;
        $this->stats['sales_created']++;
    }

    /** @param array<string, mixed> $row */
    private function findExistingRedeem(array $row, Store $store): bool
    {
        $cost = (int) round((float) ($row['cost'] ?? 0));
        $revenue = (int) round((float) ($row['revenue'] ?? 0));
        $itemKey = $this->matcher->normItem((string) ($row['item'] ?? ''));

        return PawnContract::with('item')
            ->where('store_id', $store->id)
            ->where('is_redeemed', true)
            ->get()
            ->contains(function (PawnContract $pawn) use ($itemKey, $cost, $revenue, $row) {
                if (! $pawn->item || $this->matcher->normItem($pawn->item->name) !== $itemKey) {
                    return false;
                }
                if ((int) round((float) $pawn->loan_amount) !== $cost) {
                    return false;
                }
                $buyback = (int) round((float) ($pawn->buyback_amount ?: $pawn->redemption_amount));
                if ($revenue > 0 && abs($buyback - $revenue) > 1) {
                    return false;
                }
                $date = $row['date'] instanceof Carbon ? $row['date']->toDateString() : null;

                return ! $date || $pawn->redeemed_at?->toDateString() === $date;
            });
    }

    /** @param array<string, mixed> $row */
    private function findExistingSale(array $row, Store $store): bool
    {
        $cost = (int) round((float) ($row['cost'] ?? 0));
        $revenue = (int) round((float) ($row['revenue'] ?? 0));
        $itemKey = $this->matcher->normItem((string) ($row['item'] ?? ''));
        $date = $row['date'] instanceof Carbon ? $row['date']->toDateString() : null;

        return SaleContract::with('item')
            ->where('store_id', $store->id)
            ->get()
            ->contains(function (SaleContract $sale) use ($itemKey, $cost, $revenue, $date) {
                if (! $sale->item || $this->matcher->normItem($sale->item->name) !== $itemKey) {
                    return false;
                }
                if ($revenue > 0 && (int) round((float) $sale->sale_amount) !== $revenue) {
                    return false;
                }
                $purchase = $sale->item->purchaseContract?->purchase_amount;
                if ($cost > 0 && $purchase && (int) round((float) $purchase) !== $cost) {
                    return false;
                }

                return ! $date || $sale->sale_date?->toDateString() === $date;
            });
    }

    /** @param array<string, mixed> $row */
    private function findPawnForRow(array $row, Store $store): ?PawnContract
    {
        $cost = (float) ($row['cost'] ?? 0);
        $itemKey = $this->matcher->normItem((string) ($row['item'] ?? ''));

        $candidates = PawnContract::with('item')
            ->where('store_id', $store->id)
            ->where('is_redeemed', false)
            ->orderBy('id')
            ->get();

        foreach ($candidates as $pawn) {
            if (isset($this->redeemedPawnIds[$pawn->id]) || ! $pawn->item) {
                continue;
            }
            if ($this->matcher->normItem($pawn->item->name) !== $itemKey) {
                continue;
            }
            if ($cost > 0 && abs((float) $pawn->loan_amount - $cost) > 1) {
                continue;
            }

            return $pawn;
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function findItemForSale(array $row, Store $store): ?Item
    {
        $cost = (float) ($row['cost'] ?? 0);
        $itemKey = $this->matcher->normItem((string) ($row['item'] ?? ''));

        $items = Item::with(['purchaseContract', 'saleContract', 'pawnContract'])
            ->where('store_id', $store->id)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            if (isset($this->soldItemIds[$item->id]) || $item->saleContract) {
                continue;
            }
            if ($item->pawnContract && ! $item->pawnContract->is_redeemed) {
                continue;
            }
            if ($this->matcher->normItem($item->name) !== $itemKey) {
                continue;
            }
            $purchaseAmount = (float) ($item->purchaseContract?->purchase_amount ?? $item->initial_price ?? 0);
            if ($cost > 0 && abs($purchaseAmount - $cost) > 1) {
                continue;
            }

            return $item;
        }

        return null;
    }

    private function categoryIsSale(string $category): bool
    {
        return in_array($category, ['Товары', 'Комиссионные товары'], true);
    }

    private function resolveStore(string $name): ?Store
    {
        $normalized = $this->stores->normalizeStoreName($name);
        if ($normalized === '') {
            return null;
        }

        return Store::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($normalized)])
            ->first()
            ?? $this->stores->resolveStore($name);
    }

    private function nextSaleNumber(string $base): string
    {
        $candidate = $base;
        $suffix = 2;
        while (SaleContract::where('contract_number', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function fallbackClientId(): int
    {
        $client = Client::query()->first();
        if ($client) {
            return (int) $client->id;
        }

        $client = Client::create([
            'client_type' => Client::TYPE_INDIVIDUAL,
            'last_name' => 'Розничный',
            'first_name' => 'Покупатель',
            'full_name' => 'Розничный покупатель',
            'phone' => 'profit_import_'.substr(md5((string) microtime(true)), 0, 10),
        ]);

        return (int) $client->id;
    }

    private function reset(): void
    {
        $this->redeemedPawnIds = [];
        $this->soldItemIds = [];
        $this->warnings = [];
        $this->stats = [
            'parsed' => 0,
            'redeems_applied' => 0,
            'redeems_already' => 0,
            'redeems_not_found' => 0,
            'sales_created' => 0,
            'sales_already' => 0,
            'sales_not_found' => 0,
            'skipped' => 0,
        ];
    }
}
