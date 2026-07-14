<?php

namespace App\Services\Mxl;

use App\Models\Account;
use App\Models\Item;
use App\Models\ItemStatus;
use App\Models\ItemStatusHistory;
use App\Models\PawnContract;
use App\Models\SaleContract;
use App\Models\Store;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Импорт выкупов залога и реализаций из MOXCEL с сопоставлением остатков. */
class RedemptionsMxlImportService
{
    /** @var array<int, true> */
    private array $redeemedPawnIds = [];

    /** @var array<int, true> */
    private array $soldItemIds = [];

    /** @var array<string, int> */
    private array $saleDocLineCount = [];

    private ?int $statusRedeemedId = null;

    private ?int $statusSoldId = null;

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $stats = [
        'redeemed' => 0,
        'redeem_not_found' => 0,
        'redeem_already' => 0,
        'sales_created' => 0,
        'sale_not_found' => 0,
        'sale_already' => 0,
        'returns_skipped' => 0,
        'skipped' => 0,
    ];

    public function __construct(
        private MxlRedemptionsParser $parser = new MxlRedemptionsParser,
        private ReceiptsMxlImportService $entityResolver = new ReceiptsMxlImportService,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   stats: array<string, int>,
     *   errors: list<string>,
     *   warnings: list<string>,
     *   parsed: int
     * }
     */
    public function import(string $filePath, bool $dryRun = false): array
    {
        $this->reset();

        try {
            $rows = $this->parser->parse($filePath);
        } catch (\Throwable $e) {
            $this->errors[] = $e->getMessage();

            return $this->result(false, 0);
        }

        if ($dryRun) {
            foreach ($rows as $row) {
                $this->dryRunRow($row);
            }

            return $this->result(true, count($rows));
        }

        $this->loadStatuses();

        foreach ($rows as $row) {
            try {
                DB::transaction(fn () => $this->importRow($row));
            } catch (\Throwable $e) {
                $this->errors[] = ($row['contract_number'] ?? '?').': '.$e->getMessage();
            }
        }

        return $this->result(empty($this->errors), count($rows));
    }

    /** @param array<string, mixed> $row */
    private function dryRunRow(array $row): void
    {
        $type = (string) ($row['type'] ?? '');
        if ($type === 'return') {
            $this->stats['returns_skipped']++;

            return;
        }

        $store = $this->findStore((string) ($row['store'] ?? ''));
        if (! $store) {
            $this->stats['skipped']++;

            return;
        }

        if ($type === 'redeem') {
            if ($this->findPawnForRedeem(
                (string) $row['contract_number'],
                (string) $row['item'],
                (int) $store->id,
                (string) $row['client'],
            )) {
                $this->stats['redeemed']++;
            } else {
                $this->stats['redeem_not_found']++;
            }

            return;
        }

        if ($type === 'sale') {
            if ($this->findItemForSale((string) $row['item'], (int) $store->id)) {
                $this->stats['sales_created']++;
            } else {
                $this->stats['sale_not_found']++;
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function importRow(array $row): void
    {
        $type = (string) ($row['type'] ?? '');
        if ($type === 'return') {
            $this->stats['returns_skipped']++;

            return;
        }

        $store = $this->findStore((string) ($row['store'] ?? ''));
        if (! $store) {
            $this->stats['skipped']++;

            return;
        }

        $date = $row['date'] instanceof Carbon ? $row['date'] : now();

        if ($type === 'redeem') {
            $this->processRedeem($row, $store, $date);

            return;
        }

        if ($type === 'sale') {
            $this->processSale($row, $store, $date);
        }
    }

    /** @param array<string, mixed> $row */
    private function processRedeem(array $row, Store $store, Carbon $date): void
    {
        $pawn = $this->findPawnForRedeem(
            (string) $row['contract_number'],
            (string) $row['item'],
            (int) $store->id,
            (string) $row['client'],
        );

        if (! $pawn) {
            $existing = PawnContract::query()
                ->where('store_id', $store->id)
                ->where(function ($q) use ($row) {
                    $num = (string) $row['contract_number'];
                    $q->where('contract_number', $num)->orWhere('contract_number', 'like', $num.'-%');
                })
                ->whereHas('item', fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $row['item']))]))
                ->first();

            if ($existing?->is_redeemed) {
                $this->stats['redeem_already']++;

                return;
            }

            $this->stats['redeem_not_found']++;
            $this->warnings[] = 'Выкуп не найден: '.$row['contract_number'].' — '.mb_substr((string) $row['item'], 0, 50);

            return;
        }

        $amount = (float) ($row['amount'] ?? 0);
        $pawn->update([
            'buyback_amount' => $amount > 0 ? $amount : $pawn->buyback_amount,
            'is_redeemed' => true,
            'redeemed_at' => $date,
        ]);

        $item = $pawn->item;
        if ($item && $this->statusRedeemedId) {
            $oldStatus = $item->status_id;
            $item->update(['status_id' => $this->statusRedeemedId]);
            ItemStatusHistory::create([
                'item_id' => $item->id,
                'old_status_id' => $oldStatus,
                'new_status_id' => $this->statusRedeemedId,
                'comment' => 'Выкуп по импорту MOXCEL №'.$pawn->contract_number,
            ]);
        }

        $this->redeemedPawnIds[$pawn->id] = true;
        $this->postRedeemLedger($pawn, $amount, $date);
        $this->stats['redeemed']++;
    }

    /** @param array<string, mixed> $row */
    private function processSale(array $row, Store $store, Carbon $date): void
    {
        $docNumber = (string) $row['contract_number'];
        $contractNumber = $this->nextSaleContractNumber($docNumber);

        if (SaleContract::where('contract_number', $contractNumber)->exists()) {
            $this->stats['sale_already']++;

            return;
        }

        $item = $this->findItemForSale((string) $row['item'], (int) $store->id);
        if (! $item) {
            $this->stats['sale_not_found']++;
            $this->warnings[] = 'Реализация — товар не найден в остатках: '.mb_substr((string) $row['item'], 0, 50).' ('.$docNumber.')';

            return;
        }

        $client = $this->entityResolver->resolveClient((string) $row['client']);
        if (! $client) {
            $this->stats['skipped']++;

            return;
        }

        $amount = (float) ($row['amount'] ?? 0);
        $sale = SaleContract::create([
            'contract_number' => $contractNumber,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $store->id,
            'sale_amount' => $amount,
            'sale_date' => $date,
            'lmb_data' => ['source_document' => $row['document'] ?? null],
        ]);

        if ($this->statusSoldId) {
            $oldStatus = $item->status_id;
            $item->update([
                'status_id' => $this->statusSoldId,
                'current_price' => $amount > 0 ? $amount : $item->current_price,
            ]);
            ItemStatusHistory::create([
                'item_id' => $item->id,
                'old_status_id' => $oldStatus,
                'new_status_id' => $this->statusSoldId,
                'comment' => 'Реализация по импорту MOXCEL №'.$contractNumber,
            ]);
        } else {
            $item->update(['current_price' => $amount > 0 ? $amount : $item->current_price]);
        }

        $this->soldItemIds[$item->id] = true;

        if ($amount > 0) {
            app(LedgerService::class)->post(
                Account::CODE_CASH,
                Account::CODE_SALES,
                $amount,
                $date,
                $store->id,
                'sale_contract',
                $sale->id,
                'Реализация №'.$contractNumber,
                $client->id,
            );
        }

        $this->stats['sales_created']++;
    }

    private function findPawnForRedeem(string $contractNumber, string $itemName, int $storeId, ?string $clientName = null): ?PawnContract
    {
        $candidates = PawnContract::with(['item', 'client'])
            ->where('is_redeemed', false)
            ->where('store_id', $storeId)
            ->where(function ($q) use ($contractNumber) {
                $q->where('contract_number', $contractNumber)
                    ->orWhere('contract_number', 'like', $contractNumber.'-%');
            })
            ->orderBy('id')
            ->get();

        foreach ($candidates as $pawn) {
            if (isset($this->redeemedPawnIds[$pawn->id])) {
                continue;
            }
            if ($pawn->item && $this->itemNamesMatch($pawn->item->name, $itemName)) {
                return $pawn;
            }
        }

        if ($clientName === null || trim($clientName) === '') {
            return null;
        }

        $client = $this->entityResolver->findClient($clientName);
        if (! $client) {
            return null;
        }

        $byClient = PawnContract::with('item')
            ->where('is_redeemed', false)
            ->where('store_id', $storeId)
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->get();

        foreach ($byClient as $pawn) {
            if (isset($this->redeemedPawnIds[$pawn->id])) {
                continue;
            }
            if ($pawn->item && $this->itemNamesMatch($pawn->item->name, $itemName)) {
                return $pawn;
            }
        }

        return null;
    }

    private function findItemForSale(string $itemName, int $storeId): ?Item
    {
        $items = Item::with(['pawnContract', 'saleContract', 'purchaseContract'])
            ->where('store_id', $storeId)
            ->orderBy('id')
            ->get();

        $purchaseMatches = [];
        $otherMatches = [];

        foreach ($items as $item) {
            if (isset($this->soldItemIds[$item->id])) {
                continue;
            }
            if ($item->saleContract) {
                continue;
            }
            if ($item->pawnContract && ! $item->pawnContract->is_redeemed) {
                continue;
            }
            if (! $this->itemNamesMatch($item->name, $itemName)) {
                continue;
            }
            if ($item->purchaseContract) {
                $purchaseMatches[] = $item;
            } else {
                $otherMatches[] = $item;
            }
        }

        return $purchaseMatches[0] ?? $otherMatches[0] ?? null;
    }

    private function findStore(string $name): ?Store
    {
        $normalized = $this->entityResolver->normalizeStoreName($name);
        if ($normalized === '') {
            return null;
        }

        return Store::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($normalized)])
            ->first()
            ?? $this->entityResolver->resolveStore($name);
    }

    private function nextSaleContractNumber(string $baseNumber): string
    {
        $idx = $this->saleDocLineCount[$baseNumber] ?? 0;
        $this->saleDocLineCount[$baseNumber] = $idx + 1;

        if ($idx === 0) {
            return $baseNumber;
        }

        $suffix = $idx + 1;
        $candidate = $baseNumber.'-'.$suffix;
        while (SaleContract::where('contract_number', $candidate)->exists()) {
            $suffix++;
            $candidate = $baseNumber.'-'.$suffix;
        }

        return $candidate;
    }

    private function postRedeemLedger(PawnContract $pawn, float $buybackAmount, Carbon $date): void
    {
        $loanAmount = (float) $pawn->loan_amount;
        $buyback = $buybackAmount > 0 ? $buybackAmount : (float) ($pawn->buyback_amount ?: $loanAmount);
        $interestAmount = round(max(0, $buyback - $loanAmount), 2);
        $ledger = app(LedgerService::class);
        $clientId = $pawn->client_id;

        if ($loanAmount > 0) {
            $ledger->post(
                Account::CODE_CASH,
                Account::CODE_LOANS,
                $loanAmount,
                $date,
                $pawn->store_id,
                'pawn_contract',
                $pawn->id,
                'Возврат основного долга по договору №'.$pawn->contract_number,
                $clientId,
            );
        }
        if ($interestAmount > 0) {
            $ledger->post(
                Account::CODE_CASH,
                Account::CODE_OTHER_INCOME,
                $interestAmount,
                $date,
                $pawn->store_id,
                'pawn_contract',
                $pawn->id,
                'Проценты по договору залога №'.$pawn->contract_number,
                $clientId,
            );
        }
        if ($loanAmount > 0) {
            $ledger->post(
                Account::CODE_SETTLEMENTS_OTHER,
                Account::CODE_PLEDGE,
                $loanAmount,
                $date,
                $pawn->store_id,
                'pawn_contract',
                $pawn->id,
                'Возврат товара из залога №'.$pawn->contract_number,
                $clientId,
            );
        }
    }

    private function itemNamesMatch(string $a, string $b): bool
    {
        $na = $this->normalizeItemName($a);
        $nb = $this->normalizeItemName($b);
        if ($na === $nb) {
            return true;
        }
        if (mb_strlen($na) >= 8 && mb_strlen($nb) >= 8) {
            return str_contains($na, $nb) || str_contains($nb, $na);
        }

        return false;
    }

    private function normalizeItemName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        return preg_replace('/\s+/u', ' ', $name) ?? $name;
    }

    private function loadStatuses(): void
    {
        $this->statusRedeemedId = ItemStatus::where('name', 'Выкуплен')->value('id');
        $this->statusSoldId = ItemStatus::where('name', 'Продан')->value('id');
    }

    private function reset(): void
    {
        $this->redeemedPawnIds = [];
        $this->soldItemIds = [];
        $this->saleDocLineCount = [];
        $this->errors = [];
        $this->warnings = [];
        $this->stats = [
            'redeemed' => 0,
            'redeem_not_found' => 0,
            'redeem_already' => 0,
            'sales_created' => 0,
            'sale_not_found' => 0,
            'sale_already' => 0,
            'returns_skipped' => 0,
            'skipped' => 0,
        ];
    }

    /** @return array{success: bool, stats: array<string, int>, errors: list<string>, warnings: list<string>, parsed: int} */
    private function result(bool $success, int $parsed): array
    {
        return [
            'success' => $success,
            'stats' => $this->stats,
            'errors' => $this->errors,
            'warnings' => array_slice($this->warnings, 0, 50),
            'parsed' => $parsed,
        ];
    }
}
