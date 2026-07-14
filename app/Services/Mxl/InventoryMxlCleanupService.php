<?php

namespace App\Services\Mxl;

use App\Models\CallCenterContact;
use App\Models\ClientVisit;
use App\Models\Item;
use App\Models\ItemStatusHistory;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\SaleContract;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Очистка остатков портала по инвентарке 1С: дубли, лишние позиции и склады. */
class InventoryMxlCleanupService
{
    public function __construct(
        private MxlInventoryParser $parser = new MxlInventoryParser,
        private InventoryPositionMatcher $matcher = new InventoryPositionMatcher,
        private ReceiptsMxlImportService $stores = new ReceiptsMxlImportService,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   stats: array<string, int|float>,
     *   warnings: list<string>,
     *   samples_delete: list<array<string, mixed>>,
     *   samples_keep: list<array<string, mixed>>
     * }
     */
    public function cleanup(string $inventoryFile, Carbon $asOf, bool $dryRun = false): array
    {
        $inventoryRows = array_values(array_filter(
            $this->parser->parse($inventoryFile),
            fn (array $row) => trim((string) ($row['store'] ?? '')) !== '' && trim((string) ($row['item'] ?? '')) !== '',
        ));

        $portalRows = $this->collectPortalStock();
        $plan = $this->buildPlan($inventoryRows, $portalRows);

        if (! $dryRun) {
            $this->executePlan($plan);
            $this->cleanupStores($plan['canonical_stores']);
        }

        return [
            'success' => true,
            'stats' => $plan['stats'],
            'warnings' => $plan['warnings'],
            'samples_delete' => array_slice($plan['delete_rows'], 0, 20),
            'samples_keep' => array_slice($plan['keep_rows'], 0, 10),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function collectPortalStock(): array
    {
        $rows = [];

        foreach (PawnContract::with(['item', 'client', 'store'])->where('is_redeemed', false)->get() as $pawn) {
            if (! $pawn->item) {
                continue;
            }
            $rows[] = [
                'kind' => 'pawn',
                'id' => (int) $pawn->id,
                'item_id' => (int) $pawn->item_id,
                'contract_number' => (string) $pawn->contract_number,
                'store' => (string) ($pawn->store?->name ?? ''),
                'item' => (string) $pawn->item->name,
                'amount' => (float) $pawn->loan_amount,
                'client' => (string) ($pawn->client?->full_name ?? ''),
            ];
        }

        foreach (PurchaseContract::with(['item.saleContract', 'client', 'store'])->get() as $purchase) {
            if (! $purchase->item || $purchase->item->saleContract) {
                continue;
            }
            $rows[] = [
                'kind' => 'purchase',
                'id' => (int) $purchase->id,
                'item_id' => (int) $purchase->item_id,
                'contract_number' => (string) $purchase->contract_number,
                'store' => (string) ($purchase->store?->name ?? ''),
                'item' => (string) $purchase->item->name,
                'amount' => (float) $purchase->purchase_amount,
                'client' => (string) ($purchase->client?->full_name ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $inventoryRows
     * @param list<array<string, mixed>> $portalRows
     * @return array<string, mixed>
     */
    private function buildPlan(array $inventoryRows, array $portalRows): array
    {
        $inventory = [];
        $canonicalStores = [];
        $inventoryByLoose = [];
        foreach ($inventoryRows as $index => $row) {
            $inventory[$index] = $row;
            $store = $this->matcher->normStore((string) ($row['store'] ?? ''));
            if ($store !== '') {
                $canonicalStores[$store] = $this->stores->normalizeStoreName((string) $row['store']);
            }
            $loose = $this->matcher->positionKeyLoose(
                (string) ($row['store'] ?? ''),
                (string) ($row['item'] ?? ''),
                (float) ($row['amount'] ?? 0),
            );
            $inventoryByLoose[$loose][] = $index;
        }

        $unclaimed = array_fill_keys(array_keys($inventory), true);
        $keepers = [];
        $candidates = [];

        foreach ($portalRows as $portalIndex => $portal) {
            $loose = $this->matcher->positionKeyLoose(
                (string) ($portal['store'] ?? ''),
                (string) ($portal['item'] ?? ''),
                (float) ($portal['amount'] ?? 0),
            );
            $bestIndex = null;
            $bestScore = -1;
            foreach ($inventoryByLoose[$loose] ?? [] as $invIndex) {
                if (! isset($unclaimed[$invIndex])) {
                    continue;
                }
                $invRow = $inventory[$invIndex];
                if (! $this->portalMatchesInventory($portal, $invRow)) {
                    continue;
                }
                $score = $this->matcher->scoreMatch($portal, $invRow);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $invIndex;
                }
            }
            $candidates[$portalIndex] = ['portal' => $portal, 'inv_index' => $bestIndex, 'score' => $bestScore];
        }

        uasort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        foreach ($candidates as $candidate) {
            $invIndex = $candidate['inv_index'];
            if ($invIndex === null || ! isset($unclaimed[$invIndex])) {
                continue;
            }
            unset($unclaimed[$invIndex]);
            $keepers[$candidate['portal']['kind'].'#'.$candidate['portal']['id']] = $candidate['portal'];
        }

        $keepRows = array_values($keepers);
        $deleteRows = [];
        $deletePawnIds = [];
        $deletePurchaseIds = [];
        $deleteItemIds = [];
        $keepSum = 0.0;
        $deleteSum = 0.0;

        foreach ($portalRows as $portal) {
            $key = $portal['kind'].'#'.$portal['id'];
            if (isset($keepers[$key])) {
                $keepSum += (float) $portal['amount'];

                continue;
            }
            $deleteRows[] = $portal;
            $deleteSum += (float) $portal['amount'];
            $deleteItemIds[(int) $portal['item_id']] = true;
            if ($portal['kind'] === 'pawn') {
                $deletePawnIds[] = (int) $portal['id'];
            } else {
                $deletePurchaseIds[] = (int) $portal['id'];
            }
        }

        $inventorySum = array_sum(array_map(fn (array $row) => (float) ($row['amount'] ?? 0), $inventoryRows));

        return [
            'inventory_rows' => $inventoryRows,
            'canonical_stores' => array_values($canonicalStores),
            'keep_rows' => $keepRows,
            'delete_rows' => $deleteRows,
            'delete_pawn_ids' => $deletePawnIds,
            'delete_purchase_ids' => $deletePurchaseIds,
            'delete_item_ids' => array_keys($deleteItemIds),
            'unclaimed_inventory' => count($unclaimed),
            'warnings' => [],
            'stats' => [
                'inventory_rows' => count($inventoryRows),
                'inventory_sum' => round($inventorySum, 2),
                'portal_rows_before' => count($portalRows),
                'keep_rows' => count($keepRows),
                'keep_sum' => round($keepSum, 2),
                'delete_rows' => count($deleteRows),
                'delete_sum' => round($deleteSum, 2),
                'delete_pawns' => count($deletePawnIds),
                'delete_purchases' => count($deletePurchaseIds),
                'unclaimed_inventory_rows' => count($unclaimed),
                'stores_removed' => 0,
            ],
        ];
    }

    /** @param array<string, mixed> $portal @param array<string, mixed> $inventory */
    private function portalMatchesInventory(array $portal, array $inventory): bool
    {
        $portalKeys = $this->matcher->matchKeysForPortalRow($portal);
        $inventoryKeys = $this->matcher->matchKeysForInventoryRow($inventory);

        foreach ($portalKeys as $key) {
            if (in_array($key, $inventoryKeys, true)) {
                return true;
            }
        }

        return $this->matcher->positionKeyLoose(
            (string) ($portal['store'] ?? ''),
            (string) ($portal['item'] ?? ''),
            (float) ($portal['amount'] ?? 0),
        ) === $this->matcher->positionKeyLoose(
            (string) ($inventory['store'] ?? ''),
            (string) ($inventory['item'] ?? ''),
            (float) ($inventory['amount'] ?? 0),
        );
    }

    /** @param array<string, mixed> $plan */
    private function executePlan(array $plan): void
    {
        DB::transaction(function () use ($plan) {
            if ($plan['delete_pawn_ids'] !== []) {
                ClientVisit::whereIn('pawn_contract_id', $plan['delete_pawn_ids'])->update(['pawn_contract_id' => null]);
                CallCenterContact::whereIn('pawn_contract_id', $plan['delete_pawn_ids'])->update(['pawn_contract_id' => null]);
                PawnContract::whereIn('id', $plan['delete_pawn_ids'])->delete();
            }

            if ($plan['delete_purchase_ids'] !== []) {
                ClientVisit::whereIn('purchase_contract_id', $plan['delete_purchase_ids'])->update(['purchase_contract_id' => null]);
                CallCenterContact::whereIn('purchase_contract_id', $plan['delete_purchase_ids'])->update(['purchase_contract_id' => null]);
                PurchaseContract::whereIn('id', $plan['delete_purchase_ids'])->delete();
            }

            foreach ($plan['delete_item_ids'] as $itemId) {
                $this->deleteItemIfOrphan((int) $itemId);
            }
        });
    }

    /** @param list<string> $canonicalStoreNames */
    private function cleanupStores(array $canonicalStoreNames): void
    {
        $canonical = [];
        foreach ($canonicalStoreNames as $name) {
            $canonical[$this->matcher->normStore($name)] = true;
        }

        $removed = 0;
        foreach (Store::query()->orderBy('id')->get() as $store) {
            $norm = $this->matcher->normStore((string) $store->name);
            $hasStock = PawnContract::where('store_id', $store->id)->where('is_redeemed', false)->exists()
                || PurchaseContract::where('store_id', $store->id)->whereHas('item', fn ($q) => $q->whereDoesntHave('saleContract'))->exists()
                || Item::where('store_id', $store->id)->exists();

            if ($hasStock) {
                if (! isset($canonical[$norm])) {
                    $store->update(['is_active' => false]);
                }

                continue;
            }

            $hasUsers = $store->users()->exists();
            $hasCash = $store->cashDocuments()->exists();
            if ($hasUsers || $hasCash) {
                $store->update(['is_active' => false]);

                continue;
            }

            if (! isset($canonical[$norm]) || $this->isJunkStoreName((string) $store->name)) {
                $store->delete();
                $removed++;
            }
        }

        // stats updated outside - we'll return count via re-query in command
    }

    private function isJunkStoreName(string $name): bool
    {
        $lower = mb_strtolower(trim($name));

        return str_contains($lower, 'титова галина')
            || str_contains($lower, 'титова карина')
            || str_contains($lower, 'вокзал')
            || $lower === 'ремонт'
            || $lower === 'магазин №1';
    }

    private function deleteItemIfOrphan(int $itemId): void
    {
        $item = Item::find($itemId);
        if (! $item) {
            return;
        }

        if (PawnContract::where('item_id', $itemId)->exists()
            || PurchaseContract::where('item_id', $itemId)->exists()
            || SaleContract::where('item_id', $itemId)->exists()) {
            return;
        }

        ItemStatusHistory::where('item_id', $itemId)->delete();
        $item->delete();
    }
}
