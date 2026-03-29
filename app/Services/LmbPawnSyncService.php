<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Item;
use App\Models\PawnContract;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Синхронизация действующих залогов из БД 1С в pawn_contracts.
 * Требует настройки в config/services.php (lmb_1c_pawn_sync) после определения таблиц документа «Залог» в 1С.
 */
class LmbPawnSyncService
{
    private string $connection = 'lmb_1c_pgsql';

    private array $cfg;

    /** @var array<string, mixed>|null */
    private ?array $balanceRegisterMeta = null;

    private ?string $lastBalanceRegisterSqlError = null;

    public function __construct()
    {
        $this->cfg = config('services.lmb_1c_pawn_sync', []);
    }

    /**
     * Синхронизировать залоги из 1С.
     * Возвращает ['created' => int, 'updated' => int, 'skipped' => int, 'errors' => string[]].
     *
     * @param  bool  $onlyActing  Только действующие (дата окончания >= сегодня)
     * @param  callable|null  $progress  (int $processed, int $total)
     * @param  bool  $filterByBalanceRegister  Только договоры с положительным остатком в регистре (см. balance_register в конфиге)
     */
    public function sync(bool $onlyActing = true, ?callable $progress = null, bool $filterByBalanceRegister = false): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skippedNoClient = 0;
        $skippedZeroAmount = 0;
        $skippedNoLoanDate = 0;
        $errors = [];
        $this->balanceRegisterMeta = null;
        $this->lastBalanceRegisterSqlError = null;

        $docTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($this->cfg['document_table'] ?? ''));
        if ($docTable === '') {
            $errors[] = 'Не задана таблица документа залога. Укажите LMB_1C_PAWN_DOCUMENT_TABLE в .env (см. docs/LMB_1C_TABLES_STRUCTURE_AND_SYNC.md).';

            return $this->emptyPawnSyncResult($skipped, $errors);
        }

        $contragentCol = $this->sanitizeColumn($this->cfg['contragent_column'] ?? '_fld3195rref');
        $dateCol = $this->sanitizeColumn($this->cfg['date_column'] ?? '_date');
        $numberCol = $this->sanitizeColumn($this->cfg['number_column'] ?? '');
        $amountCol = $this->sanitizeColumn($this->cfg['amount_column'] ?? '');
        if ($docTable === '_document41694x1') {
            if (trim((string) ($this->cfg['contragent_column'] ?? '')) === '') {
                $contragentCol = '_fld41695rref';
            }
            if (trim((string) ($this->cfg['amount_column'] ?? '')) === '') {
                $amountCol = '_fld41697';
            }
        }
        $percentCol = $this->sanitizeColumn($this->cfg['percent_column'] ?? '');
        $expiryCol = $this->sanitizeColumn($this->cfg['expiry_column'] ?? '');
        $buybackCol = $this->sanitizeColumn($this->cfg['buyback_amount_column'] ?? '');
        $vtTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($this->cfg['table_part_table'] ?? ''));
        $vtDocIdCol = $vtTable !== '' ? $this->sanitizeColumn($this->cfg['vt_doc_id_column'] ?? '') : '';
        $vtNomenclatureCol = $this->sanitizeColumn($this->cfg['vt_nomenclature_column'] ?? '');
        $vtAmountCol = $this->sanitizeColumn($this->cfg['vt_amount_column'] ?? '');
        $vtDescriptionCol = $this->sanitizeColumn($this->cfg['vt_description_column'] ?? '');
        $defaultStoreId = (int) ($this->cfg['default_store_id'] ?? 1);
        $storeCol = $this->sanitizeColumn($this->cfg['store_column'] ?? '');
        $storeMapping = is_array($this->cfg['store_mapping'] ?? null) ? $this->cfg['store_mapping'] : [];

        if ($contragentCol === '' || $dateCol === '') {
            $errors[] = 'Задайте contragent_column и date_column в конфиге lmb_1c_pawn_sync.';

            return $this->emptyPawnSyncResult($skipped, $errors);
        }

        $selectCols = "encode(d._idrref, 'hex') as doc_uid, d.\"{$dateCol}\" as doc_date, encode(d.\"{$contragentCol}\", 'hex') as contragent_uid";
        if ($numberCol !== '') {
            $selectCols .= ', d."'.$numberCol.'" as doc_number';
        }
        if ($amountCol !== '') {
            $selectCols .= ', d."'.$amountCol.'" as loan_amount';
        }
        if ($percentCol !== '') {
            $selectCols .= ', d."'.$percentCol.'" as loan_percent';
        }
        if ($expiryCol !== '') {
            $selectCols .= ', d."'.$expiryCol.'" as expiry_date';
        }
        if ($buybackCol !== '') {
            $selectCols .= ', d."'.$buybackCol.'" as buyback_amount';
        }
        if ($storeCol !== '') {
            $selectCols .= ', encode(d."'.$storeCol.'", \'hex\') as store_uid';
        }

        $sql = "SELECT {$selectCols} FROM public.{$docTable} d WHERE NOT d._marked";
        if ($onlyActing && $expiryCol !== '') {
            // Действующие: дата окончания >= сегодня или пустая (в 1С часто 0001-01-01)
            $sql .= ' AND (d."'.$expiryCol.'" >= CURRENT_DATE OR d."'.$expiryCol.'" < \'1900-01-01\'::timestamp)';
        }

        try {
            $docRows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            $errors[] = 'Ошибка чтения документа залога из 1С: '.$e->getMessage();

            return $this->emptyPawnSyncResult($skipped, $errors);
        }

        if ($filterByBalanceRegister) {
            $allowed = $this->loadPawnDocUidsWithPositiveRegisterBalance($docTable);
            if ($allowed === null) {
                $msg = 'Фильтр по остаткам регистра: не удалось получить список документов (проверьте balance_register в конфиге).';
                if ($this->lastBalanceRegisterSqlError !== null) {
                    $msg .= ' SQL: '.$this->lastBalanceRegisterSqlError;
                }
                $errors[] = $msg;

                return $this->emptyPawnSyncResult($skipped, $errors);
            }
            $before = count($docRows);
            $docRows = array_values(array_filter($docRows, function ($row) use ($allowed) {
                $u = strtolower(trim((string) ($row->doc_uid ?? '')));

                return $u !== '' && in_array($u, $allowed, true);
            }));
            $this->balanceRegisterMeta = [
                'register_table' => preg_replace('/[^a-z0-9_]/i', '', (string) (($this->cfg['balance_register'] ?? [])['register_table'] ?? '')),
                'docs_before_filter' => $before,
                'docs_with_balance' => count($allowed),
                'docs_after_filter' => count($docRows),
            ];
        }

        /** @var array<string, float> doc_uid hex lower => остаток по регистру (для суммы займа, если в документе 0) */
        $registerBalanceByDocUid = [];
        if ($filterByBalanceRegister) {
            $registerBalanceByDocUid = $this->loadRegisterBalanceByPawnDocUid($docTable) ?? [];
        }

        $total = 0;
        $rowsToProcess = [];

        if ($vtTable !== '' && $vtDocIdCol !== '') {
            foreach ($docRows as $docRow) {
                try {
                    // Табличная часть в 1С не имеет _idrref, только ссылку на документ и колонки строки
                    $vtSql = "SELECT v.\"{$vtDocIdCol}\" as doc_rref";
                    if ($vtNomenclatureCol !== '') {
                        $vtSql .= ', encode(v."'.$vtNomenclatureCol.'", \'hex\') as nomenclature_ref';
                    }
                    if ($vtAmountCol) {
                        $vtSql .= ", v.\"{$vtAmountCol}\" as line_amount";
                    }
                    if ($vtDescriptionCol) {
                        $vtSql .= ", v.\"{$vtDescriptionCol}\" as line_description";
                    }
                    $vtSql .= " FROM public.{$vtTable} v WHERE v.\"{$vtDocIdCol}\" = decode(?, 'hex')";
                    $lines = DB::connection($this->connection)->select($vtSql, [$docRow->doc_uid]);
                    foreach ($lines as $idx => $line) {
                        $rowsToProcess[] = [
                            'doc' => $docRow,
                            'line' => $line,
                            'line_no' => $idx,
                            'use_line_amount' => $vtAmountCol !== '',
                            'use_line_description' => $vtDescriptionCol !== '',
                        ];
                    }
                    if (count($lines) === 0) {
                        $rowsToProcess[] = ['doc' => $docRow, 'line' => null, 'line_no' => 0, 'use_line_amount' => false, 'use_line_description' => false];
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Документ {$docRow->doc_uid}: ".$e->getMessage();
                }
            }
        } else {
            foreach ($docRows as $docRow) {
                $rowsToProcess[] = ['doc' => $docRow, 'line' => null, 'line_no' => 0, 'use_line_amount' => false, 'use_line_description' => false];
            }
        }

        $total = count($rowsToProcess);

        foreach ($rowsToProcess as $idx => $row) {
            $doc = $row['doc'];
            $line = $row['line'];
            $lineNo = $row['line_no'];
            $docUid = $doc->doc_uid;
            $uniqueId = $line !== null ? $docUid.'_'.$lineNo : $docUid;

            $contragentUid = $doc->contragent_uid ?? null;
            if (is_string($contragentUid)) {
                $contragentUid = strtolower($contragentUid);
            }
            $client = $contragentUid ? Client::where('user_uid', $contragentUid)->first() : null;
            if (! $client && $contragentUid && ($this->cfg['create_placeholder_client_for_unknown'] ?? false)) {
                $client = Client::firstOrCreate(
                    ['user_uid' => $contragentUid],
                    [
                        'client_type' => Client::TYPE_INDIVIDUAL,
                        'full_name' => 'Клиент 1С (залог)',
                        'lmb_full_name' => null,
                        'phone' => '1c-pawn-'.substr(md5($contragentUid), 0, 16),
                    ]
                );
                if ($client->wasRecentlyCreated) {
                    $client->lmb_data = ['placeholder_for_pawn_sync' => true, 'contragent_uid' => $contragentUid];
                    $client->save();
                }
            }
            if (! $client) {
                $skipped++;
                $skippedNoClient++;
                if ($progress && ($idx + 1) % 100 === 0) {
                    $progress($idx + 1, $total);
                }

                continue;
            }

            $loanAmount = $this->decimalFromRow($line && $row['use_line_amount'] ? ($line->line_amount ?? null) : ($doc->loan_amount ?? null));
            $loanAmountFromRegister = false;
            if ($loanAmount <= 0 && $filterByBalanceRegister) {
                $uidKey = strtolower(trim((string) $docUid));
                $fromReg = $registerBalanceByDocUid[$uidKey] ?? null;
                $fb = $this->decimalFromRow($fromReg);
                if ($fb > 0) {
                    $loanAmount = $fb;
                    $loanAmountFromRegister = true;
                }
            }
            if ($loanAmount <= 0) {
                $skipped++;
                $skippedZeroAmount++;
                if ($progress && ($idx + 1) % 100 === 0) {
                    $progress($idx + 1, $total);
                }

                continue;
            }

            $loanDate = $this->dateFromRow($doc->doc_date ?? null);
            $expiryDate = $this->dateFromRow($doc->expiry_date ?? null);
            if (! $expiryDate && $loanDate) {
                $expiryDate = $loanDate->copy()->addDays(30);
            }
            if (! $loanDate) {
                $skipped++;
                $skippedNoLoanDate++;

                continue;
            }

            $storeId = $this->resolveStoreId($doc->store_uid ?? null, $defaultStoreId, $storeMapping);

            $itemName = 'Залог 1С '.($doc->doc_number ?? $docUid);
            $nomenclatureRef = null;
            if ($line && isset($line->nomenclature_ref)) {
                $nomenclatureRef = is_string($line->nomenclature_ref) ? strtolower($line->nomenclature_ref) : null;
            }
            if ($line && $row['use_line_description'] && ! empty(trim((string) ($line->line_description ?? '')))) {
                $itemName = trim($line->line_description);
            }

            $item = null;
            if ($nomenclatureRef) {
                $item = Item::where('lmb_ref', $nomenclatureRef)->first();
            }
            if (! $item) {
                $item = Item::create([
                    'name' => $itemName,
                    'store_id' => $storeId,
                    'barcode' => Item::generateBarcode(),
                    'lmb_ref' => $nomenclatureRef,
                    'initial_price' => $loanAmount,
                ]);
            } elseif ($loanAmount > 0 && ($item->initial_price == null || (float) $item->initial_price === 0.0)) {
                $item->initial_price = $loanAmount;
                $item->save();
            }

            $loanPercent = $this->decimalFromRow($doc->loan_percent ?? null);
            $buybackAmount = $this->decimalFromRow($doc->buyback_amount ?? null);

            $existing = PawnContract::where('lmb_doc_uid', $uniqueId)->first();
            $contractNumber = $doc->doc_number ?? PawnContract::generateContractNumber();
            if ($existing && $existing->contract_number !== $contractNumber && PawnContract::where('contract_number', $contractNumber)->where('id', '!=', $existing->id)->exists()) {
                $contractNumber = PawnContract::generateContractNumber();
            }
            if (! $existing && PawnContract::where('contract_number', $contractNumber)->exists()) {
                $contractNumber = PawnContract::generateContractNumber();
            }

            $payload = [
                'contract_number' => $contractNumber,
                'client_id' => $client->id,
                'item_id' => $item->id,
                'store_id' => $storeId,
                'loan_amount' => $loanAmount,
                'loan_percent' => $loanPercent ?: null,
                'loan_date' => $loanDate,
                'expiry_date' => $expiryDate,
                'buyback_amount' => $buybackAmount > 0 ? $buybackAmount : null,
                'is_redeemed' => false,
                'lmb_data' => array_merge([
                    'doc_uid' => $docUid,
                    'line_no' => $lineNo,
                    'synced_at' => now()->toIso8601String(),
                ], $this->balanceRegisterMeta !== null ? ['register_balance_filter' => $this->balanceRegisterMeta] : [], $loanAmountFromRegister ? ['loan_amount_source' => 'balance_register'] : []),
            ];

            try {
                if ($existing) {
                    $existing->update($payload);
                    $existing->lmb_doc_uid = $uniqueId;
                    $existing->save();
                    $updated++;
                } else {
                    $payload['lmb_doc_uid'] = $uniqueId;
                    PawnContract::create($payload);
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$uniqueId}: ".$e->getMessage();
            }

            if ($progress && (($idx + 1) % 50 === 0 || $idx + 1 === $total)) {
                $progress($idx + 1, $total);
            }
        }

        $out = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'skipped_no_client' => $skippedNoClient,
            'skipped_zero_amount' => $skippedZeroAmount,
            'skipped_no_loan_date' => $skippedNoLoanDate,
            'errors' => $errors,
        ];
        if ($this->balanceRegisterMeta !== null) {
            $out['balance_register_meta'] = $this->balanceRegisterMeta;
        }
        $this->balanceRegisterMeta = null;

        return $out;
    }

    /**
     * UID документов залога (hex), у которых в регистре накопления положительный остаток по строке справочника 252.
     *
     * @return list<string>|null Ошибка конфигурации / SQL
     */
    private function loadPawnDocUidsWithPositiveRegisterBalance(string $docTable): ?array
    {
        $br = is_array($this->cfg['balance_register'] ?? null) ? $this->cfg['balance_register'] : [];
        $regTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['register_table'] ?? ''));
        $resCol = $this->sanitizeColumn((string) ($br['resource_column'] ?? '_fld26234'));
        $ref252Table = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['ref252_table'] ?? ''));
        $regRef252Col = $this->sanitizeColumn((string) ($br['register_ref252_column'] ?? '_fld26232rref'));
        $pad = max(1, min(15, (int) ($br['match_doc_number_pad'] ?? 9)));

        if ($regTable === '' || $resCol === '' || $ref252Table === '' || $regRef252Col === '') {
            return null;
        }

        $sql = <<<SQL
WITH agg AS (
  SELECT r."{$regRef252Col}" AS ref252_id,
    SUM(r."{$resCol}" * (CASE WHEN r."_recordkind" < 0 THEN -1 ELSE 1 END)) AS bal
  FROM public.{$regTable} r
  WHERE r."_active"
  GROUP BY 1
  HAVING SUM(r."{$resCol}" * (CASE WHEN r."_recordkind" < 0 THEN -1 ELSE 1 END)) > 0.001
)
SELECT DISTINCT lower(encode(d._idrref, 'hex')) AS uid
FROM agg a
JOIN public.{$ref252Table} z ON z._idrref = a.ref252_id AND NOT z._marked
JOIN public.{$docTable} d
  ON lpad(
    COALESCE(
      NULLIF((regexp_match(trim(z._code::text), '([0-9]{4,})'))[1], ''),
      NULLIF(regexp_replace(trim(z._code::text), '[^0-9]', '', 'g'), ''),
      NULLIF((regexp_match(trim(z._code::text), '([^-]+)$'))[1], ''),
      trim(z._code::text)
    ),
    {$pad},
    '0'
  ) = lpad(
    COALESCE(
      NULLIF(regexp_replace(trim(d._number::text), '[^0-9]', '', 'g'), ''),
      trim(d._number::text)
    ),
    {$pad},
    '0'
  )
  AND NOT d._marked
SQL;

        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            $this->lastBalanceRegisterSqlError = $e->getMessage();

            return null;
        }

        $uids = [];
        foreach ($rows as $row) {
            $u = strtolower(trim((string) ($row->uid ?? '')));
            if (strlen($u) === 32) {
                $uids[] = $u;
            }
        }

        return array_values(array_unique($uids));
    }

    /**
     * Остаток регистра по документу залога (тот же join, что и для фильтра). Нужен, когда сумма в шапке документа (_fld41697 и т.д.) в выгрузке 0, а остаток по регистру > 0.
     *
     * @return array<string, float>|null
     */
    private function loadRegisterBalanceByPawnDocUid(string $docTable): ?array
    {
        $br = is_array($this->cfg['balance_register'] ?? null) ? $this->cfg['balance_register'] : [];
        $regTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['register_table'] ?? ''));
        $resCol = $this->sanitizeColumn((string) ($br['resource_column'] ?? '_fld26234'));
        $ref252Table = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['ref252_table'] ?? ''));
        $regRef252Col = $this->sanitizeColumn((string) ($br['register_ref252_column'] ?? '_fld26232rref'));
        $pad = max(1, min(15, (int) ($br['match_doc_number_pad'] ?? 9)));

        if ($regTable === '' || $resCol === '' || $ref252Table === '' || $regRef252Col === '') {
            return null;
        }

        $sql = <<<SQL
WITH agg AS (
  SELECT r."{$regRef252Col}" AS ref252_id,
    SUM(r."{$resCol}" * (CASE WHEN r."_recordkind" < 0 THEN -1 ELSE 1 END)) AS bal
  FROM public.{$regTable} r
  WHERE r."_active"
  GROUP BY 1
  HAVING SUM(r."{$resCol}" * (CASE WHEN r."_recordkind" < 0 THEN -1 ELSE 1 END)) > 0.001
)
SELECT lower(encode(d._idrref, 'hex')) AS uid,
  MAX(a.bal)::float8 AS register_balance
FROM agg a
JOIN public.{$ref252Table} z ON z._idrref = a.ref252_id AND NOT z._marked
JOIN public.{$docTable} d
  ON lpad(
    COALESCE(
      NULLIF((regexp_match(trim(z._code::text), '([0-9]{4,})'))[1], ''),
      NULLIF(regexp_replace(trim(z._code::text), '[^0-9]', '', 'g'), ''),
      NULLIF((regexp_match(trim(z._code::text), '([^-]+)$'))[1], ''),
      trim(z._code::text)
    ),
    {$pad},
    '0'
  ) = lpad(
    COALESCE(
      NULLIF(regexp_replace(trim(d._number::text), '[^0-9]', '', 'g'), ''),
      trim(d._number::text)
    ),
    {$pad},
    '0'
  )
  AND NOT d._marked
GROUP BY d._idrref
SQL;

        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            return null;
        }

        $map = [];
        foreach ($rows as $row) {
            $u = strtolower(trim((string) ($row->uid ?? '')));
            if (strlen($u) === 32) {
                $map[$u] = $this->decimalFromRow($row->register_balance ?? null);
            }
        }

        return $map;
    }

    /**
     * Определить store_id по коду склада 1С (hex): stores.lmb_store_uid или store_mapping, иначе default.
     */
    private function resolveStoreId(?string $storeUid, int $defaultStoreId, array $storeMapping): int
    {
        if ($storeUid !== null && $storeUid !== '') {
            $storeUid = strtolower($storeUid);
            $id = Store::where('lmb_store_uid', $storeUid)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
            if (isset($storeMapping[$storeUid])) {
                return (int) $storeMapping[$storeUid];
            }
        }
        $store = Store::find($defaultStoreId);
        if ($store) {
            return $store->id;
        }
        $first = Store::orderBy('id')->first();

        return $first ? $first->id : 1;
    }

    /**
     * @param  string[]  $errors
     * @return array<string, mixed>
     */
    private function emptyPawnSyncResult(int $skipped, array $errors): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'skipped' => $skipped,
            'skipped_no_client' => 0,
            'skipped_zero_amount' => 0,
            'skipped_no_loan_date' => 0,
            'errors' => $errors,
        ];
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
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }
        if (is_string($value)) {
            $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
            if ($value === '') {
                return 0.0;
            }
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
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
}
