<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Item;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Импорт из файла инвентаризации (XLS/XLSX): магазины, клиенты, товары, договоры залога и скупки.
 * Структура по docs/LMB_1C_INVENTORY_MAPPING.md: заголовок строка 4, колонки 1=Филиал, 2=Документ, 5=Наименование, ...
 */
class InventoryImportService
{
    /** Индексы колонок (1-based как в докуме). */
    private const COL_BRANCH = 1;      // Филиал

    private const COL_DOCUMENT = 2;    // Документ (ЕЛЗ-*-ДК или скупка)

    private const COL_STATUS = 3;      // Просрочка / действующий

    private const COL_CATEGORY = 4;    // Ювелирное изделие

    private const COL_NAME = 5;        // Наименование

    private const COL_DESCRIPTION = 7; // Описание

    private const COL_LOAN_DATE = 8;  // Поступление

    private const COL_TERM_DAYS = 9;   // Срок

    private const COL_EXPIRY_DATE = 10; // Возврат

    private const COL_AMOUNT = 12;     // Стоимость

    private const COL_SAMPLE = 14;     // Проба

    private const COL_WEIGHT = 15;     // Вес гр.

    private const COL_CLIENT = 19;     // Клиент

    private const COL_PHONE = 21;      // Телефон

    private const COL_SALE = 26;       // Распродажа (признак скупки/реализации)

    private int $headerRow = 4;

    private int $sheetIndex = 0;

    private array $storeCache = [];

    private array $clientCacheByPhone = [];

    private array $pawnContractNumberCount = []; // (docNumber_storeId) => count, для суффиксов -2, -3 при нескольких вещах по одному договору

    private int $placeholderPhoneCounter = 0;

    private array $errors = [];

    private array $stats = ['stores_created' => 0, 'clients_created' => 0, 'items_created' => 0, 'pawn_created' => 0, 'purchase_created' => 0, 'skipped' => 0];

    public function __construct(int $headerRow = 4, int $sheetIndex = 0)
    {
        $this->headerRow = $headerRow;
        $this->sheetIndex = $sheetIndex;
    }

    /**
     * Импорт из файла. Возвращает ['success' => bool, 'stats' => [...], 'errors' => string[]].
     */
    public function import(string $filePath, bool $dryRun = false): array
    {
        $this->errors = [];
        $this->stats = ['stores_created' => 0, 'clients_created' => 0, 'items_created' => 0, 'pawn_created' => 0, 'purchase_created' => 0, 'skipped' => 0];
        $this->storeCache = [];
        $this->clientCacheByPhone = [];
        $this->pawnContractNumberCount = [];
        $this->placeholderPhoneCounter = 0;

        $path = realpath($filePath) ?: $filePath;
        if (! is_file($path)) {
            $this->errors[] = "Файл не найден: {$filePath}";

            return ['success' => false, 'stats' => $this->stats, 'errors' => $this->errors];
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $this->errors[] = 'Не удалось прочитать файл: '.$e->getMessage();

            return ['success' => false, 'stats' => $this->stats, 'errors' => $this->errors];
        }

        $sheet = $spreadsheet->getSheet($this->sheetIndex);
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestDataColumn();
        $colCount = Coordinate::columnIndexFromString($highestCol);
        $dataStartRow = $this->headerRow + 1;

        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $cells = [];
            for ($col = 1; $col <= $colCount; $col++) {
                $coord = Coordinate::stringFromColumnIndex($col).$row;
                $v = $sheet->getCell($coord)->getValue();
                if ($v instanceof \DateTimeInterface) {
                    $v = $v->format('Y-m-d');
                }
                $cells[$col] = $v !== null ? trim((string) $v) : '';
            }

            $branchName = $this->cell($cells, self::COL_BRANCH);
            $docNumber = $this->cell($cells, self::COL_DOCUMENT);
            if ($branchName === '' && $docNumber === '') {
                continue;
            }
            if ($docNumber === '') {
                $this->stats['skipped']++;

                continue;
            }

            $isPurchase = $this->isPurchaseRow($cells);
            if ($dryRun) {
                if ($isPurchase) {
                    $this->stats['purchase_created']++;
                } else {
                    $this->stats['pawn_created']++;
                }

                continue;
            }

            $store = $this->getOrCreateStore($branchName);
            if (! $store) {
                $this->stats['skipped']++;

                continue;
            }

            $clientName = $this->cell($cells, self::COL_CLIENT);
            $phone = $this->normalizePhone($this->cell($cells, self::COL_PHONE));
            if ($clientName === '' && $phone === '') {
                $this->stats['skipped']++;

                continue;
            }

            $client = $this->getOrCreateClient($clientName, $phone);
            if (! $client) {
                $this->stats['skipped']++;

                continue;
            }

            $itemName = $this->cell($cells, self::COL_NAME);
            if ($itemName === '') {
                $itemName = ($isPurchase ? 'Скупка ' : 'Залог ').$docNumber;
            }

            $amount = $this->parseAmount($this->cell($cells, self::COL_AMOUNT));
            $loanDate = $this->parseDate($this->cell($cells, self::COL_LOAN_DATE));
            $expiryDate = $this->parseDate($this->cell($cells, self::COL_EXPIRY_DATE));
            if (! $loanDate && $isPurchase) {
                $loanDate = $expiryDate ?: now();
            }
            if (! $expiryDate) {
                $expiryDate = $loanDate?->copy()->addDays(30) ?: now();
            }

            if ($isPurchase) {
                $contract = $this->getOrCreatePurchaseContract($docNumber, $store->id, $client->id, $itemName, $this->cell($cells, self::COL_DESCRIPTION), $this->cell($cells, self::COL_SAMPLE), $this->cell($cells, self::COL_WEIGHT), $amount, $loanDate ?: now());
                if ($contract) {
                    $this->stats['purchase_created']++;
                } else {
                    $this->stats['skipped']++;
                }
            } else {
                $contract = $this->getOrCreatePawnContract($docNumber, $store->id, $client->id, $itemName, $this->cell($cells, self::COL_DESCRIPTION), $this->cell($cells, self::COL_SAMPLE), $this->cell($cells, self::COL_WEIGHT), $amount, $loanDate, $expiryDate);
                if ($contract) {
                    $this->stats['pawn_created']++;
                } else {
                    $this->stats['skipped']++;
                }
            }
        }

        return ['success' => empty($this->errors), 'stats' => $this->stats, 'errors' => $this->errors];
    }

    private function cell(array $cells, int $col): string
    {
        return $cells[$col] ?? '';
    }

    private function isPurchaseRow(array $cells): bool
    {
        $doc = $this->cell($cells, self::COL_DOCUMENT);
        $sale = $this->cell($cells, self::COL_SALE);
        if (Str::contains(mb_strtolower($doc), 'скупк') || Str::contains(mb_strtolower($doc), 'распродаж')) {
            return true;
        }
        if ($sale !== '' && $sale !== '-') {
            return true;
        }

        return false;
    }

    private function getOrCreateStore(string $name): ?Store
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $key = mb_strtolower($name);
        if (isset($this->storeCache[$key])) {
            return $this->storeCache[$key];
        }
        $store = Store::whereRaw('LOWER(name) = ?', [$key])->first();
        if (! $store) {
            $store = Store::create([
                'name' => $name,
                'address' => null,
                'is_active' => true,
            ]);
            $this->stats['stores_created']++;
        }
        $this->storeCache[$key] = $store;

        return $store;
    }

    private function getOrCreateClient(string $fullName, string $phone): ?Client
    {
        $fullName = trim($fullName);
        if ($phone === '') {
            $this->placeholderPhoneCounter++;
            $phone = 'no_phone_'.$this->placeholderPhoneCounter.'_'.Str::limit(md5($fullName ?: uniqid('', true)), 8);
        }
        $cacheKey = $phone;
        if (isset($this->clientCacheByPhone[$cacheKey])) {
            return $this->clientCacheByPhone[$cacheKey];
        }
        $client = Client::where('phone', $phone)->first();
        if (! $client) {
            $parts = $this->parseFio($fullName);
            $client = Client::create([
                'client_type' => Client::TYPE_INDIVIDUAL,
                'last_name' => $parts['last_name'],
                'first_name' => $parts['first_name'],
                'patronymic' => $parts['patronymic'],
                'full_name' => $fullName ?: 'Клиент без имени',
                'phone' => $phone,
            ]);
            $this->stats['clients_created']++;
        } else {
            if ($fullName !== '' && trim($client->getRawOriginal('full_name') ?? '') === '') {
                $parts = $this->parseFio($fullName);
                $client->update([
                    'last_name' => $parts['last_name'],
                    'first_name' => $parts['first_name'],
                    'patronymic' => $parts['patronymic'],
                    'full_name' => $fullName,
                ]);
            }
        }
        $this->clientCacheByPhone[$cacheKey] = $client;

        return $client;
    }

    private function parseFio(string $fio): array
    {
        $fio = preg_replace('/\s+/u', ' ', trim($fio));
        $parts = explode(' ', $fio, 3);

        return [
            'last_name' => $parts[0] ?? '',
            'first_name' => $parts[1] ?? '',
            'patronymic' => $parts[2] ?? '',
        ];
    }

    /** Одна строка файла = один договор залога с одним товаром (в файле один ЕЛЗ может идти несколькими строками — несколько вещей). Для уникальности contract_number добавляем суффикс -2, -3. */
    private function getOrCreatePawnContract(string $docNumber, int $storeId, int $clientId, string $itemName, string $description, string $sampleStr, string $weightStr, float $amount, ?Carbon $loanDate, ?Carbon $expiryDate): ?PawnContract
    {
        $key = $docNumber.'_'.$storeId;
        $idx = $this->pawnContractNumberCount[$key] ?? 0;
        $this->pawnContractNumberCount[$key] = $idx + 1;
        $contractNumber = $idx === 0 ? $docNumber : $docNumber.'-'.($idx + 1);
        if (PawnContract::where('contract_number', $contractNumber)->exists()) {
            while (PawnContract::where('contract_number', $contractNumber)->exists()) {
                $this->pawnContractNumberCount[$key]++;
                $contractNumber = $docNumber.'-'.$this->pawnContractNumberCount[$key];
            }
        }

        $item = Item::create([
            'name' => mb_substr($itemName, 0, 255),
            'description' => $description ? mb_substr($description, 0, 1000) : null,
            'sample' => $this->parseSample($sampleStr),
            'weight_grams' => $this->parseWeight($weightStr),
            'initial_price' => $amount,
            'store_id' => $storeId,
            'barcode' => Item::generateBarcode(),
        ]);
        $this->stats['items_created']++;

        return PawnContract::create([
            'contract_number' => $contractNumber,
            'client_id' => $clientId,
            'item_id' => $item->id,
            'store_id' => $storeId,
            'loan_amount' => $amount,
            'loan_date' => $loanDate ?: now(),
            'expiry_date' => $expiryDate ?: now()->addDays(30),
            'is_redeemed' => false,
        ]);
    }

    private function getOrCreatePurchaseContract(string $docNumber, int $storeId, int $clientId, string $itemName, string $description, string $sampleStr, string $weightStr, float $amount, $purchaseDate): ?PurchaseContract
    {
        $existing = PurchaseContract::where('contract_number', $docNumber)->where('store_id', $storeId)->first();
        if ($existing) {
            return $existing;
        }

        $item = Item::create([
            'name' => mb_substr($itemName, 0, 255),
            'description' => $description ? mb_substr($description, 0, 1000) : null,
            'sample' => $this->parseSample($sampleStr),
            'weight_grams' => $this->parseWeight($weightStr),
            'initial_price' => $amount,
            'store_id' => $storeId,
            'barcode' => Item::generateBarcode(),
        ]);
        $this->stats['items_created']++;

        return PurchaseContract::create([
            'contract_number' => $docNumber,
            'client_id' => $clientId,
            'item_id' => $item->id,
            'store_id' => $storeId,
            'purchase_amount' => $amount,
            'purchase_date' => $purchaseDate instanceof Carbon ? $purchaseDate : now(),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 10) {
            if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
                return '7'.$digits;
            }
            if (str_starts_with($digits, '8')) {
                return '7'.substr($digits, 1);
            }

            return $digits;
        }

        return $phone;
    }

    private function parseAmount(string $value): float
    {
        $value = preg_replace('/[^\d.,]/', '', str_replace(',', '.', $value));

        return (float) $value;
    }

    private function parseSample(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 16);
    }

    private function parseWeight(string $value): ?float
    {
        $value = preg_replace('/[^\d.,]/', '', str_replace(',', '.', trim($value)));

        return $value === '' ? null : (float) $value;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $months = [
            'янв' => 1, 'фев' => 2, 'февр' => 2, 'мар' => 3, 'апр' => 4, 'май' => 5, 'июн' => 6,
            'июл' => 7, 'авг' => 8, 'сен' => 9, 'окт' => 10, 'ноя' => 11, 'нояб' => 11, 'дек' => 12,
        ];
        $valueLower = mb_strtolower($value);
        foreach ($months as $ru => $num) {
            if (Str::contains($valueLower, $ru)) {
                $day = null;
                if (preg_match('/^(\d{1,2})\s*[а-яё]/u', $value, $m)) {
                    $day = (int) $m[1];
                }
                $year = (int) date('Y');
                if (preg_match('/\b(20\d{2})\b/', $value, $y)) {
                    $year = (int) $y[1];
                } elseif (preg_match('/\b(\d{2})\s*[а-яё]/u', $value, $y) && (int) $y[1] <= 12) {
                    // только месяц без года — текущий год
                }
                if ($day !== null && $day >= 1 && $day <= 31) {
                    try {
                        return Carbon::createFromDate($year, $num, $day);
                    } catch (\Throwable $e) {
                        return null;
                    }
                }
                try {
                    return Carbon::createFromDate($year, $num, 1);
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
