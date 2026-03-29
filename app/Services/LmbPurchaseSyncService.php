<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Item;
use App\Models\PurchaseContract;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Синхронизация договоров скупки из БД 1С (_document389x1) в purchase_contracts и items.
 * Опционально создаёт клиентов из справочника контрагентов 1С и заполняет товар метаданными витрины/номенклатуры.
 */
class LmbPurchaseSyncService
{
    private string $connection = 'lmb_1c_pgsql';

    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('services.lmb_1c_purchase_sync', []);
    }

    /**
     * Синхронизировать договоры скупки из 1С.
     * Возвращает ['created' => int, 'updated' => int, 'skipped' => int, 'skipped_no_client' => int, 'errors' => string[],
     *   'clients_from_1c' => array{created: int, updated: int, skipped: int, errors: array}|null].
     *
     * @param  bool  $dryRun  true — не создавать и не обновлять записи, только подсчёт
     */
    public function sync(?callable $progress = null, bool $dryRun = false): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skippedNoClient = 0;
        $errors = [];

        $docTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($this->cfg['document_table'] ?? ''));
        $clientsFrom1c = null;

        if ($docTable === '') {
            $errors[] = 'Не задана таблица документа скупки. Укажите LMB_1C_PURCHASE_DOCUMENT_TABLE в .env.';

            return ['created' => 0, 'updated' => 0, 'skipped' => $skipped, 'skipped_no_client' => 0, 'errors' => $errors, 'clients_from_1c' => null];
        }

        $createMissingClients = filter_var($this->cfg['create_missing_clients'] ?? true, FILTER_VALIDATE_BOOL);
        $skipZeroAmount = filter_var($this->cfg['skip_zero_amount'] ?? true, FILTER_VALIDATE_BOOL);

        $contragentCol = $this->sanitizeColumn($this->cfg['contragent_column'] ?? '_fld9626rref');
        $dateCol = $this->sanitizeColumn($this->cfg['date_column'] ?? '_date_time');
        $numberCol = $this->sanitizeColumn($this->cfg['number_column'] ?? '_number');
        $amountCol = $this->sanitizeColumn($this->cfg['amount_column'] ?? '_fld9631');
        $nameColumns = $this->cfg['name_columns'] ?? ['_fld9638', '_fld9643', '_fld9650'];
        $defaultStoreId = (int) ($this->cfg['default_store_id'] ?? 1);
        $storeCol = $this->sanitizeColumn($this->cfg['store_column'] ?? '');

        $export = config('services.lmb_1c_purchase_export', []);
        $storesCfg = config('services.lmb_1c_stores_sync', []);
        $nomTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($export['nomenclature_table'] ?? '_reference45'));
        $nomRefCol = $this->sanitizeColumn($export['nomenclature_ref_column'] ?? '_fld9632rref');
        $attTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($export['attachment_table'] ?? '_reference367x1'));
        $attCol = $this->sanitizeColumn($export['attachment_ref_column'] ?? '_fld46109rref');
        $att2Col = $this->sanitizeColumn($export['attachment2_ref_column'] ?? '_fld46110rref');
        $branchAltTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($export['branch_alt_table'] ?? '_reference201x1'));
        $branchAltRefCol = $this->sanitizeColumn($export['branch_alt_ref_column'] ?? '_fld9644rref');
        $storeTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($storesCfg['table'] ?? '_reference197'));
        $storeNameCol = $this->sanitizeColumn($storesCfg['name_column'] ?? '_description');

        $selectCols = "encode(d._idrref, 'hex') as doc_uid, d.\"{$dateCol}\" as doc_date, encode(d.\"{$contragentCol}\", 'hex') as contragent_uid";
        $selectCols .= ', d."'.$numberCol.'" as doc_number';
        if ($amountCol !== '') {
            $selectCols .= ', d."'.$amountCol.'" as doc_amount';
        }
        if ($storeCol !== '') {
            $selectCols .= ', encode(d."'.$storeCol.'", \'hex\') as store_uid';
        }
        foreach ($nameColumns as $col) {
            $safe = $this->sanitizeColumn($col);
            if ($safe !== '') {
                $selectCols .= ', d."'.$safe.'" as col_'.preg_replace('/[^a-z0-9_]/i', '_', $safe);
            }
        }

        $selectCols .= ", encode(d.\"{$nomRefCol}\", 'hex') as nomenclature_uid_hex";
        $selectCols .= ', nom._fld1702::text as nom_fld1702, nom._fld1699::text as nom_fld1699, nom._description::text as nom_description_short';
        if ($storeCol !== '') {
            $selectCols .= ", st.\"{$storeNameCol}\"::text as store_branch_name";
        } else {
            $selectCols .= ', NULL::text as store_branch_name';
        }
        $selectCols .= ', br._description::text as branch_alt_description, br._fld5347::text as branch_alt_comment';
        $selectCols .= ", encode(d.\"{$attCol}\", 'hex') as attachment_1_uid_hex";
        $selectCols .= ', att1._description::text as attachment_1_description, att1._folder as attachment_1_is_folder';
        $selectCols .= ", encode(d.\"{$att2Col}\", 'hex') as attachment_2_uid_hex";
        $selectCols .= ', att2._description::text as attachment_2_description, att2._folder as attachment_2_is_folder';

        $joinStore = $storeCol !== ''
            ? "LEFT JOIN public.{$storeTable} st ON st._idrref = d.\"{$storeCol}\" AND NOT st._marked"
            : '';

        $sql = "
            SELECT {$selectCols}
            FROM public.{$docTable} d
            {$joinStore}
            LEFT JOIN public.{$branchAltTable} br ON br._idrref = d.\"{$branchAltRefCol}\" AND NOT br._marked
            LEFT JOIN public.{$nomTable} nom ON nom._idrref = d.\"{$nomRefCol}\" AND NOT nom._marked
            LEFT JOIN public.{$attTable} att1 ON att1._idrref = d.\"{$attCol}\" AND NOT att1._marked
            LEFT JOIN public.{$attTable} att2 ON att2._idrref = d.\"{$att2Col}\" AND NOT att2._marked
            WHERE NOT d._marked
            ORDER BY d.\"{$dateCol}\"
        ";

        try {
            $docRows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            $errors[] = 'Ошибка чтения документа скупки из 1С: '.$e->getMessage();

            return ['created' => 0, 'updated' => 0, 'skipped' => $skipped, 'skipped_no_client' => 0, 'errors' => $errors, 'clients_from_1c' => null];
        }

        $total = count($docRows);
        if ($total === 0) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'skipped_no_client' => 0, 'errors' => $errors, 'clients_from_1c' => null];
        }

        if ($createMissingClients && ! $dryRun) {
            $uids = [];
            foreach ($docRows as $doc) {
                $u = strtolower(trim((string) ($doc->contragent_uid ?? '')));
                if (strlen($u) !== 32) {
                    continue;
                }
                if (! Client::where('user_uid', $u)->exists()) {
                    $uids[$u] = true;
                }
            }
            if ($uids !== []) {
                $clientsFrom1c = app(LmbContragentSyncService::class)->syncByUids(array_keys($uids));
            }
        }

        $fallbackStoreId = $defaultStoreId;
        if (! Store::find($fallbackStoreId)) {
            $first = Store::orderBy('id')->first();
            $fallbackStoreId = $first ? $first->id : 1;
        }

        foreach ($docRows as $idx => $doc) {
            $storeId = $this->resolveStoreId($doc->store_uid ?? null, $fallbackStoreId);

            $docUid = strtolower(trim((string) ($doc->doc_uid ?? '')));

            $contragentUid = strtolower(trim((string) ($doc->contragent_uid ?? '')));
            $client = strlen($contragentUid) === 32 ? Client::where('user_uid', $contragentUid)->first() : null;
            if (! $client) {
                $skipped++;
                $skippedNoClient++;
                if ($progress && (($idx + 1) % 50 === 0 || $idx + 1 === $total)) {
                    $progress($idx + 1, $total);
                }

                continue;
            }

            $amount = $this->decimalFromRow($doc->doc_amount ?? null);
            if ($skipZeroAmount && $amount <= 0) {
                $skipped++;
                if ($progress && (($idx + 1) % 50 === 0 || $idx + 1 === $total)) {
                    $progress($idx + 1, $total);
                }

                continue;
            }

            $purchaseDate = $this->dateFromRow($doc->doc_date ?? null);
            if (! $purchaseDate) {
                $skipped++;
                if ($progress && (($idx + 1) % 50 === 0 || $idx + 1 === $total)) {
                    $progress($idx + 1, $total);
                }

                continue;
            }

            $itemName = $this->resolveItemName($doc, $nameColumns, $docUid, $doc->doc_number ?? null);
            $contractLmbData = $this->buildContractLmbData($doc, $docUid);
            $itemPatch = $this->buildItemPatchFromDoc($doc);

            $existing = PurchaseContract::where('lmb_doc_uid', $docUid)->first();
            $contractNumber = $doc->doc_number ?? PurchaseContract::generateContractNumber();
            if ($existing) {
                if ($dryRun) {
                    $updated++;
                    if ($progress && (($idx + 1) % 50 === 0 || $idx + 1 === $total)) {
                        $progress($idx + 1, $total);
                    }

                    continue;
                }
                $item = $existing->item;
                if (! $item) {
                    $item = Item::create(array_merge([
                        'name' => $itemName,
                        'store_id' => $storeId,
                        'barcode' => Item::generateBarcode(),
                    ], $itemPatch));
                    $this->applyParsedMetalFromName($item, $itemName);
                    $existing->update(['item_id' => $item->id]);
                } else {
                    $itemUp = array_merge(['name' => $itemName, 'store_id' => $storeId], $itemPatch);
                    $item->update($itemUp);
                    $this->applyParsedMetalFromName($item->fresh(), $itemName);
                }
                $existing->update([
                    'client_id' => $client->id,
                    'store_id' => $storeId,
                    'purchase_amount' => $amount,
                    'purchase_date' => $purchaseDate,
                    'lmb_data' => $contractLmbData,
                ]);
                $updated++;
            } else {
                if ($dryRun) {
                    $created++;
                    if ($progress && (($idx + 1) % 50 === 0 || $idx + 1 === $total)) {
                        $progress($idx + 1, $total);
                    }

                    continue;
                }
                if (PurchaseContract::where('contract_number', $contractNumber)->exists()) {
                    $contractNumber = PurchaseContract::generateContractNumber();
                }
                $item = Item::create(array_merge([
                    'name' => $itemName,
                    'store_id' => $storeId,
                    'barcode' => Item::generateBarcode(),
                ], $itemPatch));
                $this->applyParsedMetalFromName($item, $itemName);
                PurchaseContract::create([
                    'contract_number' => $contractNumber,
                    'lmb_doc_uid' => $docUid,
                    'client_id' => $client->id,
                    'item_id' => $item->id,
                    'store_id' => $storeId,
                    'purchase_amount' => $amount,
                    'purchase_date' => $purchaseDate,
                    'lmb_data' => $contractLmbData,
                ]);
                $created++;
            }

            if ($progress && (($idx + 1) % 50 === 0 || $idx + 1 === $total)) {
                $progress($idx + 1, $total);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'skipped_no_client' => $skippedNoClient,
            'errors' => $errors,
            'clients_from_1c' => $clientsFrom1c,
        ];
    }

    /**
     * @param  array<int, string>  $nameColumns
     */
    private function resolveItemName(object $doc, array $nameColumns, string $docUid, $docNumber): string
    {
        foreach ($nameColumns as $col) {
            $safe = $this->sanitizeColumn($col);
            if ($safe === '') {
                continue;
            }
            $prop = 'col_'.preg_replace('/[^a-z0-9_]/i', '_', $safe);
            $val = $doc->$prop ?? null;
            if ($val !== null && trim((string) $val) !== '') {
                return mb_substr(trim((string) $val), 0, 255);
            }
        }

        $nomHint = $this->clean1cText($doc->nom_fld1702 ?? null);
        if ($nomHint !== '' && ! preg_match('/^рубл/i', $nomHint) && mb_strlen($nomHint) <= 200) {
            return mb_substr($nomHint, 0, 255);
        }
        $nomHint = $this->clean1cText($doc->nom_fld1699 ?? null);
        if ($nomHint !== '' && ! preg_match('/^рубл|^российский рубль$/i', $nomHint)) {
            return mb_substr($nomHint, 0, 255);
        }

        return 'Скупка 1С '.($docNumber ?? $docUid);
    }

    private function buildContractLmbData(object $doc, string $docUid): array
    {
        $att1Hex = $this->normalizeHexRef($doc->attachment_1_uid_hex ?? null);
        $att2Hex = $this->normalizeHexRef($doc->attachment_2_uid_hex ?? null);
        $nomHex = $this->normalizeHexRef($doc->nomenclature_uid_hex ?? null);

        return [
            'doc_uid' => $docUid,
            '1c_doc_number' => $this->clean1cText($doc->doc_number ?? null) ?: null,
            'synced_at' => now()->toIso8601String(),
            'store_branch_name_1c' => $this->clean1cText($doc->store_branch_name ?? null) ?: null,
            'branch_alt_1c' => $this->clean1cText($doc->branch_alt_description ?? null) ?: null,
            'branch_alt_comment_1c' => $this->clean1cText($doc->branch_alt_comment ?? null) ?: null,
            'nomenclature_uid_hex' => $nomHex,
            'attachment_1c' => [
                'uid_hex' => $att1Hex,
                'description' => $this->clean1cText($doc->attachment_1_description ?? null) ?: null,
                'is_folder' => $doc->attachment_1_is_folder ?? null,
            ],
            'attachment_2_1c' => [
                'uid_hex' => $att2Hex,
                'description' => $this->clean1cText($doc->attachment_2_description ?? null) ?: null,
                'is_folder' => $doc->attachment_2_is_folder ?? null,
            ],
            'photo_note' => 'В типовой выгрузке 1С в PostgreSQL файлы изображений часто отсутствуют (binarydata пуст, в _reference367x1 нет файлов — только папки витрин). Загрузите фото вручную в карточку товара при необходимости.',
        ];
    }

    /** @return array{name: string, description?: string, lmb_ref?: string, photos?: null} */
    private function buildItemPatchFromDoc(object $doc): array
    {
        $lines = [];
        $att = $this->clean1cText($doc->attachment_1_description ?? null);
        if ($att !== '') {
            $lines[] = 'Витрина / вложение 1С: '.$att;
        }
        $br = $this->clean1cText($doc->branch_alt_description ?? null);
        if ($br !== '') {
            $lines[] = 'Подразделение (ответственный) 1С: '.$br;
        }
        $bc = $this->clean1cText($doc->branch_alt_comment ?? null);
        if ($bc !== '') {
            $lines[] = $bc;
        }
        $store = $this->clean1cText($doc->store_branch_name ?? null);
        if ($store !== '') {
            $lines[] = 'Склад 1С: '.$store;
        }

        $patch = [];
        if ($lines !== []) {
            $patch['description'] = implode("\n", $lines);
        }

        $nomHex = $this->normalizeHexRef($doc->nomenclature_uid_hex ?? null);
        if ($nomHex !== null) {
            $patch['lmb_ref'] = $nomHex;
        }

        return $patch;
    }

    private function applyParsedMetalFromName(Item $item, string $itemName): void
    {
        $parsed = LmbPurchaseItemNameService::parseItemString($itemName);
        $up = array_filter([
            'metal' => $parsed['metal'],
            'sample' => $parsed['sample'],
            'weight_grams' => $parsed['weight_grams'],
        ]);
        if ($up !== []) {
            $item->update($up);
        }
    }

    private function clean1cText(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        $s = trim($s, " \t\n\r\0\x0B\xC2\xA0");

        return $s;
    }

    private function normalizeHexRef(?string $hex): ?string
    {
        if ($hex === null || $hex === '') {
            return null;
        }
        $h = strtolower(preg_replace('/[^a-f0-9]/', '', $hex));

        return (strlen($h) === 32 && ! preg_match('/^0{32}$/', $h)) ? $h : null;
    }

    private function sanitizeColumn(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/i', '', $name);
    }

    private function decimalFromRow($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function dateFromRow($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveStoreId(?string $storeUid, int $defaultStoreId): int
    {
        if ($storeUid !== null && $storeUid !== '') {
            $storeUid = strtolower($storeUid);
            $id = Store::where('lmb_store_uid', $storeUid)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }
        $store = Store::find($defaultStoreId);

        return $store ? $store->id : $defaultStoreId;
    }
}
