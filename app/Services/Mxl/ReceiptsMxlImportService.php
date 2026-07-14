<?php

namespace App\Services\Mxl;

use App\Models\Client;
use App\Models\Item;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\Store;
use Carbon\Carbon;

/** Импорт поступлений из MOXCEL: точки, клиенты, залоги и скупки. */
class ReceiptsMxlImportService
{
    /** @var array<string, Store> */
    private array $storeCache = [];

    /** @var array<string, Client> */
    private array $clientCache = [];

    /** @var array<string, int> */
    private array $contractLineCount = [];

    private int $placeholderPhoneCounter = 0;

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, int> */
    private array $stats = [
        'stores_created' => 0,
        'clients_created' => 0,
        'clients_matched' => 0,
        'items_created' => 0,
        'pawn_created' => 0,
        'purchase_created' => 0,
        'skipped' => 0,
    ];

    public function __construct(
        private MxlReceiptsParser $parser = new MxlReceiptsParser,
    ) {}

    /**
     * @return array{success: bool, stats: array<string, int>, errors: list<string>, parsed: int}
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

        foreach ($rows as $row) {
            if ($dryRun) {
                $this->stats[$row['type'] === 'purchase' ? 'purchase_created' : 'pawn_created']++;

                continue;
            }

            $this->importRow($row);
        }

        return $this->result(empty($this->errors), count($rows));
    }

    /** @param array<string, mixed> $row */
    private function importRow(array $row): void
    {
        $storeName = (string) ($row['store'] ?? '');
        $clientName = (string) ($row['client'] ?? '');
        $docNumber = (string) ($row['contract_number'] ?? '');
        $itemName = (string) ($row['item'] ?? '');
        $amount = (float) ($row['amount'] ?? 0);

        if ($storeName === '' || $docNumber === '' || $clientName === '' || $itemName === '') {
            $this->stats['skipped']++;

            return;
        }

        $store = $this->getOrCreateStore($storeName);
        if (! $store) {
            $this->stats['skipped']++;

            return;
        }

        $client = $this->getOrCreateClient($clientName);
        if (! $client) {
            $this->stats['skipped']++;

            return;
        }

        $contractNumber = $this->nextContractNumber($docNumber, (int) $store->id);
        $date = $row['date'] instanceof Carbon ? $row['date'] : now();

        if ($row['type'] === 'purchase') {
            if (PurchaseContract::where('contract_number', $contractNumber)->exists()) {
                $this->stats['skipped']++;

                return;
            }
            $this->createPurchase($contractNumber, $store, $client, $itemName, $amount, $date);
        } else {
            if (PawnContract::where('contract_number', $contractNumber)->exists()) {
                $this->stats['skipped']++;

                return;
            }
            $this->createPawn($contractNumber, $store, $client, $itemName, $amount, $date);
        }
    }

    private function createPawn(string $contractNumber, Store $store, Client $client, string $itemName, float $amount, Carbon $date): void
    {
        $item = Item::create([
            'name' => mb_substr($itemName, 0, 255),
            'initial_price' => $amount,
            'store_id' => $store->id,
            'barcode' => Item::generateBarcode(),
        ]);
        $this->stats['items_created']++;

        PawnContract::create([
            'contract_number' => $contractNumber,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $store->id,
            'loan_amount' => $amount,
            'loan_date' => $date,
            'expiry_date' => $date->copy()->addDays(30),
            'is_redeemed' => false,
        ]);
        $this->stats['pawn_created']++;
    }

    private function createPurchase(string $contractNumber, Store $store, Client $client, string $itemName, float $amount, Carbon $date): void
    {
        $item = Item::create([
            'name' => mb_substr($itemName, 0, 255),
            'initial_price' => $amount,
            'store_id' => $store->id,
            'barcode' => Item::generateBarcode(),
        ]);
        $this->stats['items_created']++;

        PurchaseContract::create([
            'contract_number' => $contractNumber,
            'client_id' => $client->id,
            'item_id' => $item->id,
            'store_id' => $store->id,
            'purchase_amount' => $amount,
            'purchase_date' => $date,
        ]);
        $this->stats['purchase_created']++;
    }

    private function nextContractNumber(string $baseNumber, int $storeId): string
    {
        $key = $baseNumber.'_'.$storeId;
        $idx = $this->contractLineCount[$key] ?? 0;
        $this->contractLineCount[$key] = $idx + 1;

        if ($idx === 0) {
            return $baseNumber;
        }

        $suffix = $idx + 1;
        $candidate = $baseNumber.'-'.$suffix;
        while (
            PawnContract::where('contract_number', $candidate)->exists()
            || PurchaseContract::where('contract_number', $candidate)->exists()
        ) {
            $suffix++;
            $candidate = $baseNumber.'-'.$suffix;
        }

        return $candidate;
    }

    private function getOrCreateStore(string $name): ?Store
    {
        $name = $this->normalizeStoreName($name);
        if ($name === '') {
            return null;
        }

        $key = mb_strtolower($name);
        if (isset($this->storeCache[$key])) {
            return $this->storeCache[$key];
        }

        $store = Store::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$key])
            ->first();

        if (! $store) {
            $store = Store::create([
                'name' => $name,
                'address' => $name,
                'is_active' => true,
            ]);
            $this->stats['stores_created']++;
        }

        return $this->storeCache[$key] = $store;
    }

    private function getOrCreateClient(string $fullName): ?Client
    {
        $fullName = $this->normalizeName($fullName);
        if ($fullName === '') {
            return null;
        }

        $key = mb_strtolower($fullName);
        if (isset($this->clientCache[$key])) {
            return $this->clientCache[$key];
        }

        $client = $this->findClientByName($fullName);
        if ($client) {
            $this->stats['clients_matched']++;

            return $this->clientCache[$key] = $client;
        }

        $parts = $this->parseFio($fullName);
        $this->placeholderPhoneCounter++;
        $phone = 'mxl_'.substr(md5($fullName), 0, 12).'_'.$this->placeholderPhoneCounter;

        $client = Client::create([
            'client_type' => Client::TYPE_INDIVIDUAL,
            'last_name' => $parts['last_name'],
            'first_name' => $parts['first_name'],
            'patronymic' => $parts['patronymic'],
            'full_name' => $fullName,
            'phone' => $phone,
        ]);
        $this->stats['clients_created']++;

        return $this->clientCache[$key] = $client;
    }

    private function findClientByName(string $fullName): ?Client
    {
        $normalized = mb_strtolower($fullName);

        $byFull = Client::query()
            ->whereRaw('LOWER(TRIM(full_name)) = ?', [$normalized])
            ->orWhereRaw('LOWER(TRIM(lmb_full_name)) = ?', [$normalized])
            ->first();
        if ($byFull) {
            return $byFull;
        }

        $parts = $this->parseFio($fullName);
        if ($parts['last_name'] === '') {
            return null;
        }

        $query = Client::query()->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower($parts['last_name'])]);
        if ($parts['first_name'] !== '') {
            $query->whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower($parts['first_name'])]);
        }
        if ($parts['patronymic'] !== '') {
            $query->whereRaw('LOWER(TRIM(patronymic)) = ?', [mb_strtolower($parts['patronymic'])]);
        }

        return $query->first();
    }

    /** @return array{last_name: string, first_name: string, patronymic: string} */
    private function parseFio(string $fio): array
    {
        $fio = preg_replace('/\s+/u', ' ', trim($fio)) ?? '';
        $parts = explode(' ', $fio, 3);

        return [
            'last_name' => $parts[0] ?? '',
            'first_name' => $parts[1] ?? '',
            'patronymic' => $parts[2] ?? '',
        ];
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? '';
    }

    public function resolveStore(string $name): ?Store
    {
        return $this->getOrCreateStore($name);
    }

    public function resolveClient(string $fullName): ?Client
    {
        return $this->getOrCreateClient($fullName);
    }

    public function findClient(string $fullName): ?Client
    {
        $fullName = $this->normalizeName($fullName);
        if ($fullName === '') {
            return null;
        }

        return $this->findClientByName($fullName);
    }

    public function normalizeStoreName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $lower = mb_strtolower($name);
        if (str_contains($lower, 'витрина')) {
            if (str_contains($lower, 'горский')) {
                return 'Горский, 1';
            }
            if (str_contains($lower, 'станиславского')) {
                return 'Станиславского, 29';
            }
        }
        if (str_contains($lower, 'сейф') && str_contains($lower, 'горский')) {
            return 'Горский, 1';
        }
        if (str_starts_with($lower, 'комиссионка')) {
            $stripped = preg_replace('/^комиссионка\s+/ui', '', $name);

            return $stripped !== '' ? trim($stripped) : $name;
        }

        return $name;
    }

    private function reset(): void
    {
        $this->storeCache = [];
        $this->clientCache = [];
        $this->contractLineCount = [];
        $this->placeholderPhoneCounter = 0;
        $this->errors = [];
        $this->stats = [
            'stores_created' => 0,
            'clients_created' => 0,
            'clients_matched' => 0,
            'items_created' => 0,
            'pawn_created' => 0,
            'purchase_created' => 0,
            'skipped' => 0,
        ];
    }

    /** @return array{success: bool, stats: array<string, int>, errors: list<string>, parsed: int} */
    private function result(bool $success, int $parsed): array
    {
        return [
            'success' => $success,
            'stats' => $this->stats,
            'errors' => $this->errors,
            'parsed' => $parsed,
        ];
    }
}
