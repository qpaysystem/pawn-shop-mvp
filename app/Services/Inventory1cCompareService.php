<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Сравнение строк залога из файла инвентаризации (XLS) с документом залога в PostgreSQL 1С и регистром остатков (как в lmb:sync-pawn-contracts --with-register-balance).
 */
class Inventory1cCompareService
{
    private const COL_BRANCH = 1;

    private const COL_DOCUMENT = 2;

    private const COL_NAME = 5;

    private const COL_AMOUNT = 12;

    private const COL_CLIENT = 19;

    private const COL_SALE = 26;

    private string $connection = 'lmb_1c_pgsql';

    /**
     * @return array{
     *   success: bool,
     *   error?: string,
     *   excel_pawn_rows?: int,
     *   distinct_doc_keys_excel?: int,
     *   doc_found_rows?: int,
     *   doc_missing_rows?: int,
     *   register_positive_rows?: int,
     *   register_miss_rows?: int,
     *   register_unknown_rows?: int,
     *   doc_table?: string,
     *   register_table?: string,
     *   ref252_table?: string,
     *   register_query_failed?: bool,
     *   samples_doc_missing?: list<array{doc:string, branch:string, name:string}>,
     *   samples_register_miss?: list<array{doc:string, number_key:string, number_1c:string, ref252_code:string, name:string}>
     * }
     */
    public function compare(string $filePath, int $headerRow = 4, int $sheetIndex = 0, int $maxSamples = 20): array
    {
        $path = realpath($filePath) ?: $filePath;
        if (! is_file($path)) {
            return ['success' => false, 'error' => "Файл не найден: {$filePath}"];
        }

        $cfg = config('services.lmb_1c_pawn_sync', []);
        $docTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['document_table'] ?? ''));
        if ($docTable === '') {
            return ['success' => false, 'error' => 'Не задан LMB_1C_PAWN_DOCUMENT_TABLE в .env.'];
        }

        $numberCol = $this->sanitizeColumn((string) ($cfg['number_column'] ?? '_number'));
        if ($numberCol === '') {
            return ['success' => false, 'error' => 'Не задан number_column для документа залога.'];
        }

        $pad = max(1, min(15, (int) (($cfg['balance_register']['match_doc_number_pad'] ?? 9))));

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Не удалось прочитать Excel: '.$e->getMessage()];
        }

        $sheet = $spreadsheet->getSheet($sheetIndex);
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestDataColumn();
        $colCount = Coordinate::columnIndexFromString($highestCol);
        $dataStart = $headerRow + 1;

        $pawnRows = [];
        for ($row = $dataStart; $row <= $highestRow; $row++) {
            $cells = [];
            for ($col = 1; $col <= $colCount; $col++) {
                $coord = Coordinate::stringFromColumnIndex($col).$row;
                $v = $sheet->getCell($coord)->getValue();
                if ($v instanceof \DateTimeInterface) {
                    $v = $v->format('Y-m-d');
                }
                $cells[$col] = $v !== null ? trim((string) $v) : '';
            }
            $branch = $cells[self::COL_BRANCH] ?? '';
            $doc = $cells[self::COL_DOCUMENT] ?? '';
            if ($branch === '' && $doc === '') {
                continue;
            }
            if ($doc === '') {
                continue;
            }
            if ($this->isPurchaseRow($cells)) {
                continue;
            }
            $key = $this->inventoryDocToPaddedKey($doc, $pad);
            $pawnRows[] = [
                'doc' => $doc,
                'number_key' => $key,
                'branch' => $branch,
                'name' => $cells[self::COL_NAME] ?? '',
                'amount' => $cells[self::COL_AMOUNT] ?? '',
                'client' => $cells[self::COL_CLIENT] ?? '',
            ];
        }

        $excelPawnRows = count($pawnRows);
        $distinctKeys = array_unique(array_filter(array_column($pawnRows, 'number_key')));

        try {
            DB::connection($this->connection)->getPdo();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Нет подключения к БД 1С (lmb_1c_pgsql): '.$e->getMessage()];
        }

        $docMap = $this->load1cDocumentNumberKeyMap($docTable, $numberCol, $pad);
        if ($docMap === null) {
            return ['success' => false, 'error' => 'Ошибка SQL при чтении документов залога из 1С.'];
        }

        $br = is_array($cfg['balance_register'] ?? null) ? $cfg['balance_register'] : [];
        $registerTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['register_table'] ?? ''));
        $ref252Table = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['ref252_table'] ?? ''));

        $registerUids = null;
        $docToRef252 = [];
        $registerQueryFailed = false;
        if ($registerTable !== '' && $ref252Table !== '') {
            $loaded = $this->loadRegisterPositiveDocUids($docTable, $br, $numberCol);
            if ($loaded === null) {
                $registerQueryFailed = true;
                $registerUids = [];
            } else {
                $registerUids = $loaded;
            }
            $refMap = $this->loadDocUidToRef252Code($docTable, $br, $numberCol);
            $docToRef252 = $refMap === null ? [] : $refMap;
        }

        $registerSet = null;
        if ($registerUids !== null && ! $registerQueryFailed) {
            $registerSet = array_flip($registerUids);
        }

        $docFoundRows = 0;
        $docMissingRows = 0;
        $regPosRows = 0;
        $regMissRows = 0;
        $regUnknownRows = 0;
        $samplesMiss = [];
        $samplesRegMiss = [];

        foreach ($pawnRows as $pr) {
            $key = $pr['number_key'];
            if ($key === null || $key === '') {
                $docMissingRows++;
                if (count($samplesMiss) < $maxSamples) {
                    $samplesMiss[] = ['doc' => $pr['doc'], 'branch' => $pr['branch'], 'name' => $pr['name']];
                }

                continue;
            }
            $uid = $docMap['by_key'][$key] ?? null;
            if ($uid === null) {
                $docMissingRows++;
                if (count($samplesMiss) < $maxSamples) {
                    $samplesMiss[] = ['doc' => $pr['doc'], 'branch' => $pr['branch'], 'name' => $pr['name']];
                }

                continue;
            }
            $docFoundRows++;
            if ($registerUids === null || $registerQueryFailed) {
                $regUnknownRows++;

                continue;
            }
            $uidLower = strtolower($uid);
            if ($registerSet !== null && isset($registerSet[$uidLower])) {
                $regPosRows++;
            } else {
                $regMissRows++;
                if (count($samplesRegMiss) < $maxSamples) {
                    $samplesRegMiss[] = [
                        'doc' => $pr['doc'],
                        'number_key' => $key,
                        'number_1c' => $docMap['number_raw'][$uidLower] ?? '',
                        'ref252_code' => $docToRef252[$uidLower] ?? '—',
                        'name' => $pr['name'],
                    ];
                }
            }
        }

        return [
            'success' => true,
            'excel_pawn_rows' => $excelPawnRows,
            'distinct_doc_keys_excel' => count($distinctKeys),
            'doc_found_rows' => $docFoundRows,
            'doc_missing_rows' => $docMissingRows,
            'register_positive_rows' => $regPosRows,
            'register_miss_rows' => $regMissRows,
            'register_unknown_rows' => $regUnknownRows,
            'doc_table' => $docTable,
            'register_table' => $registerTable ?: '(не задан)',
            'ref252_table' => $ref252Table ?: '(не задан)',
            'register_query_failed' => $registerQueryFailed,
            'samples_doc_missing' => $samplesMiss,
            'samples_register_miss' => $samplesRegMiss,
        ];
    }

    /**
     * @return array{by_key: array<string, string>, number_raw: array<string, string>}|null
     */
    private function load1cDocumentNumberKeyMap(string $docTable, string $numberCol, int $pad): ?array
    {
        $sql = <<<SQL
SELECT
  lower(encode(d._idrref, 'hex')) AS doc_uid,
  trim(d."{$numberCol}"::text) AS number_raw,
  lpad(
    COALESCE(
      NULLIF(regexp_replace(trim(d."{$numberCol}"::text), '[^0-9]', '', 'g'), ''),
      trim(d."{$numberCol}"::text)
    ),
    {$pad},
    '0'
  ) AS number_key
FROM public.{$docTable} d
WHERE NOT d._marked
SQL;

        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            return null;
        }

        $byKey = [];
        $numberRaw = [];
        foreach ($rows as $row) {
            $uid = strtolower(trim((string) ($row->doc_uid ?? '')));
            $key = trim((string) ($row->number_key ?? ''));
            if ($uid === '' || strlen($uid) !== 32 || $key === '') {
                continue;
            }
            $numberRaw[$uid] = trim((string) ($row->number_raw ?? ''));
            if (! isset($byKey[$key])) {
                $byKey[$key] = $uid;
            }
        }

        return ['by_key' => $byKey, 'number_raw' => $numberRaw];
    }

    /**
     * @return list<string>|null
     */
    private function loadRegisterPositiveDocUids(string $docTable, array $br, string $numberCol): ?array
    {
        $regTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['register_table'] ?? ''));
        $resCol = $this->sanitizeColumn((string) ($br['resource_column'] ?? '_fld26234'));
        $ref252Table = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['ref252_table'] ?? ''));
        $regRef252Col = $this->sanitizeColumn((string) ($br['register_ref252_column'] ?? '_fld26232rref'));
        $pad = max(1, min(15, (int) ($br['match_doc_number_pad'] ?? 9)));

        if ($regTable === '' || $resCol === '' || $ref252Table === '' || $regRef252Col === '') {
            return [];
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
      NULLIF(regexp_replace(trim(d."{$numberCol}"::text), '[^0-9]', '', 'g'), ''),
      trim(d."{$numberCol}"::text)
    ),
    {$pad},
    '0'
  )
  AND NOT d._marked
SQL;

        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
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
     * @return array<string, string>|null doc_uid lower => ref252 _code
     */
    private function loadDocUidToRef252Code(string $docTable, array $br, string $numberCol): ?array
    {
        $regTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['register_table'] ?? ''));
        $resCol = $this->sanitizeColumn((string) ($br['resource_column'] ?? '_fld26234'));
        $ref252Table = preg_replace('/[^a-z0-9_]/i', '', (string) ($br['ref252_table'] ?? ''));
        $regRef252Col = $this->sanitizeColumn((string) ($br['register_ref252_column'] ?? '_fld26232rref'));
        $pad = max(1, min(15, (int) ($br['match_doc_number_pad'] ?? 9)));

        if ($regTable === '' || $resCol === '' || $ref252Table === '' || $regRef252Col === '') {
            return [];
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
SELECT DISTINCT ON (d._idrref)
  lower(encode(d._idrref, 'hex')) AS doc_uid,
  trim(z._code::text) AS ref252_code
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
      NULLIF(regexp_replace(trim(d."{$numberCol}"::text), '[^0-9]', '', 'g'), ''),
      trim(d."{$numberCol}"::text)
    ),
    {$pad},
    '0'
  )
  AND NOT d._marked
ORDER BY d._idrref, a.bal DESC
SQL;

        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            return null;
        }

        $map = [];
        foreach ($rows as $row) {
            $u = strtolower(trim((string) ($row->doc_uid ?? '')));
            if (strlen($u) === 32) {
                $map[$u] = trim((string) ($row->ref252_code ?? ''));
            }
        }

        return $map;
    }

    private function inventoryDocToPaddedKey(string $doc, int $pad): ?string
    {
        $t = trim($doc);
        if ($t === '') {
            return null;
        }
        $core = '';
        if (preg_match('/(\d{4,})/', $t, $m)) {
            $core = $m[1];
        } else {
            $core = preg_replace('/\D/', '', $t);
            if ($core === '') {
                if (preg_match('/([^-]+)$/', $t, $m2)) {
                    $core = preg_replace('/\D/', '', $m2[1]);
                }
            }
        }
        if ($core === '') {
            return null;
        }

        return str_pad($core, $pad, '0', STR_PAD_LEFT);
    }

    private function isPurchaseRow(array $cells): bool
    {
        $doc = $cells[self::COL_DOCUMENT] ?? '';
        $sale = $cells[self::COL_SALE] ?? '';
        if (Str::contains(mb_strtolower($doc), 'скупк') || Str::contains(mb_strtolower($doc), 'распродаж')) {
            return true;
        }
        if ($sale !== '' && $sale !== '-') {
            return true;
        }

        return false;
    }

    private function sanitizeColumn(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/i', '', $name);
    }
}
