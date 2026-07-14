<?php

namespace App\Services\Mxl;

use App\Models\Client;
use App\Models\Item;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\SaleContract;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Сверка инвентарки MOXCEL с остатками портала на дату. */
class InventoryMxlReconcileService
{
  /** @var array<string, Store> */
  private array $storeCache = [];

  /** @var array<string, Client> */
  private array $clientCache = [];

  private int $placeholderPhoneCounter = 0;

  /** @var array<string, int> */
  private array $contractLineCount = [];

  /** @var list<string> */
  private array $warnings = [];

  /** @var array<string, int> */
  private array $stats = [
    'inventory_rows' => 0,
    'matched' => 0,
    'missing' => 0,
    'purchases_created' => 0,
    'pawns_created' => 0,
    'skipped' => 0,
  ];

  public function __construct(
    private MxlInventoryParser $parser = new MxlInventoryParser,
    private ReceiptsMxlImportService $stores = new ReceiptsMxlImportService,
  ) {}

  /**
   * @return array{
   *   success: bool,
   *   stats: array<string, int>,
   *   warnings: list<string>,
   *   missing_samples: list<array<string, mixed>>
   * }
   */
  public function reconcile(
    string $inventoryFile,
    Carbon $asOf,
    bool $createMissingAsPurchaseOnly = true,
    bool $dryRun = false,
  ): array {
    $this->reset();
    $rows = $this->parser->parse($inventoryFile);
    $this->stats['inventory_rows'] = count($rows);

    $stockKeys = $this->stockKeysAsOf($asOf);
    $missing = [];

    foreach ($rows as $row) {
      if (trim((string) ($row['store'] ?? '')) === '' || trim((string) ($row['item'] ?? '')) === '') {
        $this->stats['skipped']++;

        continue;
      }

      $key = $this->positionKey(
        (string) $row['store'],
        (string) $row['document'],
        (string) $row['item'],
        (float) ($row['amount'] ?? 0),
      );

      if ($this->isMatched($row, $stockKeys)) {
        $this->stats['matched']++;

        continue;
      }

      $this->stats['missing']++;
      $missing[] = $row;

      if ($dryRun) {
        continue;
      }

      $this->createMissing($row, $asOf, $createMissingAsPurchaseOnly);
    }

    return [
      'success' => true,
      'stats' => $this->stats,
      'warnings' => $this->warnings,
      'missing_samples' => array_slice($missing, 0, 20),
    ];
  }

  /**
   * @return array<string, true>
   */
  private function stockKeysAsOf(Carbon $asOf): array
  {
    $keys = [];
    $asOfDate = $asOf->toDateString();
    $asOfEnd = $asOf->copy()->endOfDay();

    $pawns = PawnContract::with(['item', 'client', 'store'])
      ->whereDate('loan_date', '<=', $asOfDate)
      ->where(function ($q) use ($asOfEnd) {
        $q->where('is_redeemed', false)
          ->orWhere('redeemed_at', '>', $asOfEnd);
      })
      ->get();

    foreach ($pawns as $pawn) {
      if (! $pawn->item) {
        continue;
      }
      $storeName = $pawn->store?->name ?? '';
      $clientName = $pawn->client?->full_name ?? '';
      $amount = (float) $pawn->loan_amount;
      $itemName = $pawn->item->name;
      $keys[$this->positionKey($storeName, $pawn->contract_number, $itemName, $amount)] = true;
      $keys[$this->positionKeyLoose($storeName, $itemName, $amount)] = true;
      $keys[$this->positionKeyWithClient($storeName, $itemName, $amount, $clientName)] = true;
      $keys[$this->positionKeyClientItem($itemName, $amount, $clientName)] = true;
    }

    $purchases = PurchaseContract::with(['client', 'item.saleContract', 'store'])
      ->whereDate('purchase_date', '<=', $asOfDate)
      ->get();

    foreach ($purchases as $purchase) {
      if (! $purchase->item) {
        continue;
      }
      $sale = $purchase->item->saleContract;
      if ($sale && $sale->sale_date && $sale->sale_date->lte($asOfEnd)) {
        continue;
      }
      $storeName = $purchase->store?->name ?? '';
      $clientName = $purchase->client?->full_name ?? '';
      $amount = (float) $purchase->purchase_amount;
      $itemName = $purchase->item->name;
      $keys[$this->positionKey($storeName, $purchase->contract_number, $itemName, $amount)] = true;
      $keys[$this->positionKeyLoose($storeName, $itemName, $amount)] = true;
      $keys[$this->positionKeyWithClient($storeName, $itemName, $amount, $clientName)] = true;
      $keys[$this->positionKeyClientItem($itemName, $amount, $clientName)] = true;
    }

    return $keys;
  }

  /** @param array<string, mixed> $row */
  private function isMatched(array $row, array $stockKeys): bool
  {
    $store = (string) $row['store'];
    $doc = (string) $row['document'];
    $item = (string) $row['item'];
    $amount = (float) ($row['amount'] ?? 0);
    $client = (string) ($row['client'] ?? '');

    foreach ([
      $this->positionKey($store, $doc, $item, $amount),
      $this->positionKeyLoose($store, $item, $amount),
      $this->positionKeyWithClient($store, $item, $amount, $client),
      $this->positionKeyClientItem($item, $amount, $client),
    ] as $key) {
      if (isset($stockKeys[$key])) {
        return true;
      }
    }

    return false;
  }

  private function positionKey(string $store, string $document, string $item, float $amount): string
  {
    return implode('|', [
      $this->normStore($store),
      mb_strtolower(trim($document)),
      $this->normItem($item),
      (string) (int) round($amount),
    ]);
  }

  private function positionKeyLoose(string $store, string $item, float $amount): string
  {
    return implode('|', [
      $this->normStore($store),
      $this->normItem($item),
      (string) (int) round($amount),
    ]);
  }

  private function positionKeyWithClient(string $store, string $item, float $amount, string $client): string
  {
    return implode('|', [
      $this->normStore($store),
      $this->normItem($item),
      (string) (int) round($amount),
      $this->normClient($client),
    ]);
  }

  private function positionKeyClientItem(string $item, float $amount, string $client): string
  {
    return implode('|', [
      $this->normItem($item),
      (string) (int) round($amount),
      $this->normClient($client),
    ]);
  }

  /** @param array<string, mixed> $row */
  private function createMissing(array $row, Carbon $asOf, bool $purchaseOnly): void
  {
    $store = $this->getOrCreateStore((string) $row['store']);
    if (! $store) {
      $this->stats['skipped']++;

      return;
    }

    $client = $this->getOrCreateClient((string) $row['client'], (string) ($row['phone'] ?? ''));
    if (! $client) {
      $this->stats['skipped']++;

      return;
    }

    $itemName = mb_substr((string) $row['item'], 0, 255);
    $amount = (float) ($row['amount'] ?? 0);
    $doc = trim((string) $row['document']);
    $contractNumber = $this->nextContractNumber($doc, (int) $store->id);
    $receiptDate = ($row['loan_date'] instanceof Carbon ? $row['loan_date'] : null) ?? $asOf;

    $asPawn = ! $purchaseOnly && ($row['type'] ?? '') === 'pawn';

    if ($asPawn) {
      if (PawnContract::where('contract_number', $contractNumber)->exists()) {
        $this->stats['skipped']++;

        return;
      }
      $item = Item::create([
        'name' => $itemName,
        'initial_price' => $amount,
        'store_id' => $store->id,
        'barcode' => Item::generateBarcode(),
      ]);
      PawnContract::create([
        'contract_number' => $contractNumber,
        'client_id' => $client->id,
        'item_id' => $item->id,
        'store_id' => $store->id,
        'loan_amount' => $amount,
        'loan_date' => $receiptDate,
        'expiry_date' => ($row['expiry_date'] instanceof Carbon ? $row['expiry_date'] : $receiptDate->copy()->addDays(30)),
        'is_redeemed' => false,
      ]);
      $this->stats['pawns_created']++;

      return;
    }

    if ($amount <= 0) {
      $this->stats['skipped']++;
      $this->warnings[] = 'Пропуск с нулевой суммой: '.$contractNumber.' — '.mb_substr($itemName, 0, 40);

      return;
    }

    if (PurchaseContract::where('contract_number', $contractNumber)->exists()) {
      $this->stats['skipped']++;

      return;
    }

    $item = Item::create([
      'name' => $itemName,
      'initial_price' => $amount,
      'store_id' => $store->id,
      'barcode' => Item::generateBarcode(),
    ]);
    PurchaseContract::create([
      'contract_number' => $contractNumber,
      'client_id' => $client->id,
      'item_id' => $item->id,
      'store_id' => $store->id,
      'purchase_amount' => $amount,
      'purchase_date' => $receiptDate,
    ]);
    $this->stats['purchases_created']++;
  }

  private function getOrCreateStore(string $name): ?Store
  {
    $name = $this->stores->normalizeStoreName($name);
    if ($name === '') {
      return null;
    }
    $key = mb_strtolower($name);
    if (isset($this->storeCache[$key])) {
      return $this->storeCache[$key];
    }
    $store = Store::query()->whereRaw('LOWER(TRIM(name)) = ?', [$key])->first();
    if (! $store) {
      $store = Store::create(['name' => $name, 'address' => $name, 'is_active' => true]);
    }

    return $this->storeCache[$key] = $store;
  }

  private function getOrCreateClient(string $fullName, string $phone): ?Client
  {
    $fullName = preg_replace('/\s+/u', ' ', trim($fullName)) ?? '';
    if ($fullName === '') {
      $fullName = 'Клиент из инвентарки';
    }

    $phoneDigits = preg_replace('/\D+/u', '', $phone) ?? '';
    if (strlen($phoneDigits) >= 10) {
      $key = $phoneDigits;
      if (isset($this->clientCache[$key])) {
        return $this->clientCache[$key];
      }
      $client = Client::where('phone', 'like', '%'.substr($phoneDigits, -10))->first();
      if ($client) {
        return $this->clientCache[$key] = $client;
      }
    }

    $nameKey = mb_strtolower($fullName);
    if (isset($this->clientCache[$nameKey])) {
      return $this->clientCache[$nameKey];
    }

    $client = Client::query()->whereRaw('LOWER(TRIM(full_name)) = ?', [$nameKey])->first();
    if (! $client) {
      $parts = explode(' ', $fullName, 3);
      $this->placeholderPhoneCounter++;
      $client = Client::create([
        'client_type' => Client::TYPE_INDIVIDUAL,
        'last_name' => $parts[0] ?? '',
        'first_name' => $parts[1] ?? '',
        'patronymic' => $parts[2] ?? '',
        'full_name' => $fullName,
        'phone' => 'inv_'.substr(md5($fullName), 0, 10).'_'.$this->placeholderPhoneCounter,
      ]);
    }

    return $this->clientCache[$nameKey] = $client;
  }

  private function normStore(string $store): string
  {
    return mb_strtolower($this->stores->normalizeStoreName($store));
  }

  private function normItem(string $item): string
  {
    $item = mb_strtolower(trim($item));
    $item = preg_replace('/\s+/u', ' ', $item) ?? '';

    return $item;
  }

  private function normClient(string $client): string
  {
    $client = mb_strtolower(trim($client));
    $client = preg_replace('/\s+/u', ' ', $client) ?? '';

    return $client;
  }

  private function nextContractNumber(string $baseNumber, int $storeId): string
  {
    $key = $baseNumber.'_'.$storeId;
    $idx = $this->contractLineCount[$key] ?? 0;
    $this->contractLineCount[$key] = $idx + 1;

    $candidate = $idx === 0 ? $baseNumber : $baseNumber.'-'.($idx + 1);
    while (
      PurchaseContract::where('contract_number', $candidate)->exists()
      || PawnContract::where('contract_number', $candidate)->exists()
    ) {
      $idx++;
      $this->contractLineCount[$key] = $idx + 1;
      $candidate = $baseNumber.'-'.($idx + 1);
    }

    return $candidate;
  }

  private function reset(): void
  {
    $this->storeCache = [];
    $this->clientCache = [];
    $this->contractLineCount = [];
    $this->placeholderPhoneCounter = 0;
    $this->warnings = [];
    $this->stats = [
      'inventory_rows' => 0,
      'matched' => 0,
      'missing' => 0,
      'purchases_created' => 0,
      'pawns_created' => 0,
      'skipped' => 0,
    ];
  }
}
