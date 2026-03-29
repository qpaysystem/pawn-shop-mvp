<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Доп. реквизиты удостоверения личности из БД 1С: текст из документа выдачи (серия/кем выдан/дата),
 * адрес регистрации из табличной части контактов (vt3220).
 */
class Lmb1cIdentityDetailsService
{
    private string $connection = 'lmb_1c_pgsql';

    /**
     * @param  array<int, object{uid?: string, name_raw?: mixed, desc_raw?: mixed}>  $contragentRows  строки из sync (uid + ФИО)
     * @return array{addresses: array<string, string>, addresses_by_fio: array<string, string>, narratives: array<string, array<string, mixed>>}
     */
    public function preloadForContragentRows(array $contragentRows): array
    {
        $cfg = config('services.lmb_1c_contragent_sync', []);
        $vtTable = preg_replace('/[^a-z0-9_]/i', '', $cfg['vt3220_table'] ?? '_reference122_vt3220x1');
        $vtRefCol = preg_replace('/[^a-z0-9_]/i', '', $cfg['vt3220_contragent_ref_column'] ?? '_reference122_idrref');
        $lineCols = $this->resolveVt3220LineColumns($cfg);
        $lineSqlExpr = $this->buildVt3220LineSqlExpression($lineCols);
        $refTable = preg_replace('/[^a-z0-9_]/i', '', $cfg['table'] ?? '_reference122x1');
        $nameCol = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['name_column'] ?? '_fld3178'));
        $docTable = preg_replace('/[^a-z0-9_]/i', '', $cfg['identity_document_table'] ?? '_document517x1');
        $docFioCol = preg_replace('/[^a-z0-9_]/i', '', $cfg['identity_document_fio_column'] ?? '_fld15354');
        $docTextCol = preg_replace('/[^a-z0-9_]/i', '', $cfg['identity_document_passport_text_column'] ?? '_fld15357');
        $docDateCol = preg_replace('/[^a-z0-9_]/i', '', $cfg['identity_document_date_column'] ?? '_date_time');

        $uids = [];
        $fioKeys = [];
        foreach ($contragentRows as $row) {
            $uid = $row->uid ?? '';
            if ($uid !== '') {
                $uids[$uid] = true;
            }
            $name = $this->normalizeFioKey($this->pickFio($row));
            if ($name !== '') {
                $fioKeys[$name] = true;
            }
        }
        $uidList = array_keys($uids);
        $fioList = array_keys($fioKeys);

        $addresses = $this->loadAddressMap($vtTable, $vtRefCol, $lineSqlExpr, $uidList);

        $maxFioForByFio = (int) ($cfg['identity_preload_max_fio_for_address_by_fio'] ?? 600);
        $addressesByFio = [];
        if (count($fioList) <= $maxFioForByFio && $maxFioForByFio > 0) {
            $addressesByFio = $this->loadAddressMapByFio($refTable, $nameCol, $vtTable, $vtRefCol, $lineSqlExpr, $fioList);
        }

        $maxFioNarr = (int) ($cfg['identity_preload_max_fio_for_narratives'] ?? 2000);
        $narratives = [];
        if ($maxFioNarr <= 0 || count($fioList) <= $maxFioNarr) {
            $narratives = $this->loadPassportNarrativeMapSimple($docTable, $docFioCol, $docTextCol, $docDateCol, $fioList);
        }

        return [
            'addresses' => $addresses,
            'addresses_by_fio' => $addressesByFio,
            'narratives' => $narratives,
        ];
    }

    public function fioKeyFromName(string $name): string
    {
        return $this->normalizeFioKey($name);
    }

    /**
     * @param  array<string, mixed>  $identityBlock  результат preloadForContragentRows()
     */
    public function mergeIntoPayload(array $identityBlock, string $uidHex, string $fioKey): array
    {
        $out = [];
        $addr = '';
        if ($uidHex !== '' && ! empty($identityBlock['addresses'][$uidHex])) {
            $addr = (string) $identityBlock['addresses'][$uidHex];
        }
        $byFio = $identityBlock['addresses_by_fio'] ?? [];
        if (trim($addr) === '' && $fioKey !== '' && ! empty($byFio[$fioKey])) {
            $addr = (string) $byFio[$fioKey];
        }
        if (trim($addr) !== '') {
            $out['lmb_registration_address'] = mb_substr(trim($addr), 0, 2000);
        }
        $nar = $identityBlock['narratives'][$fioKey] ?? null;
        if (is_array($nar)) {
            if (! empty($nar['lmb_identity_document_type'])) {
                $out['lmb_identity_document_type'] = $nar['lmb_identity_document_type'];
            }
            if (! empty($nar['lmb_passport_issued_by'])) {
                $out['lmb_passport_issued_by'] = $nar['lmb_passport_issued_by'];
            }
            if (! empty($nar['lmb_passport_issued_at'])) {
                $out['lmb_passport_issued_at'] = $nar['lmb_passport_issued_at'];
            }
        }

        return $out;
    }

    /**
     * Разбор строки вида: «паспорту серии 5012 № 999652, выдан 13.08.2012 Отделом УФМС ... 540003»
     *
     * @return array{lmb_identity_document_type: ?string, lmb_passport_issued_by: ?string, lmb_passport_issued_at: ?string}
     */
    public function parsePassportNarrative(string $text): array
    {
        $text = trim($text);
        $result = [
            'lmb_identity_document_type' => null,
            'lmb_passport_issued_by' => null,
            'lmb_passport_issued_at' => null,
        ];
        if ($text === '') {
            return $result;
        }
        if (preg_match('/паспорт/u', $text)) {
            $result['lmb_identity_document_type'] = 'Паспорт гражданина РФ';
        }
        if (preg_match('/выдан\s+(\d{2}\.\d{2}\.\d{4})\s+(.+)/us', $text, $m)) {
            try {
                $d = Carbon::createFromFormat('d.m.Y', $m[1]);
                $result['lmb_passport_issued_at'] = $d->format('Y-m-d');
            } catch (\Throwable $e) {
                // ignore
            }
            $by = trim($m[2]);
            $by = preg_replace('/\s+\d{6}\s*$/u', '', $by);
            $by = trim($by, " \t\n\r\0\x0B,");
            if ($by !== '') {
                $result['lmb_passport_issued_by'] = mb_substr($by, 0, 2000);
            }
        }

        return $result;
    }

    private function pickFio(object $row): string
    {
        $name = trim((string) ($row->name_raw ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($row->desc_raw ?? ''));
    }

    private function normalizeFioKey(string $fio): string
    {
        $fio = preg_replace('/\s+/u', ' ', trim($fio));

        return $fio === '' ? '' : mb_strtolower($fio, 'UTF-8');
    }

    /**
     * @return array<string, string> uid hex => address
     */
    private function loadAddressMap(string $vtTable, string $vtRefCol, string $lineSqlExpr, array $uidHexList): array
    {
        if ($uidHexList === [] || $vtTable === '') {
            return [];
        }
        $map = [];
        foreach (array_chunk($uidHexList, 800) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "
                SELECT encode(vt.\"{$vtRefCol}\", 'hex') AS uid,
                  ({$lineSqlExpr}) AS line,
                  vt.\"_lineno3221\" AS ln
                FROM public.{$vtTable} vt
                WHERE encode(vt.\"{$vtRefCol}\", 'hex') IN ({$in})
                ORDER BY uid, vt.\"_lineno3221\" NULLS LAST
            ";
            try {
                $rows = DB::connection($this->connection)->select($sql, $chunk);
            } catch (\Throwable $e) {
                continue;
            }
            $byUid = [];
            foreach ($rows as $r) {
                $u = $r->uid ?? '';
                if ($u === '') {
                    continue;
                }
                if (! isset($byUid[$u])) {
                    $byUid[$u] = [];
                }
                $byUid[$u][] = ['line' => (string) ($r->line ?? ''), 'ln' => (int) ($r->ln ?? 0)];
            }
            foreach ($byUid as $u => $lines) {
                $addr = $this->pickAddressLine($lines);
                if ($addr !== '') {
                    $map[$u] = $addr;
                }
            }
        }

        return $map;
    }

    /**
     * Резерв: адрес по ФИО из ref122 + vt3220 (если по UID пусто или рассинхрон).
     *
     * @return array<string, string> fio_key lower => address
     */
    private function loadAddressMapByFio(
        string $refTable,
        string $nameCol,
        string $vtTable,
        string $vtRefCol,
        string $lineSqlExpr,
        array $fioKeysLower
    ): array {
        if ($fioKeysLower === [] || $refTable === '' || $vtTable === '') {
            return [];
        }
        $fioSql = $nameCol !== ''
            ? "lower(trim(regexp_replace(trim(COALESCE(NULLIF(trim(c.\"{$nameCol}\"::text), ''), c._description::text)), '\\s+', ' ', 'g')))"
            : "lower(trim(regexp_replace(trim(c._description::text), '\\s+', ' ', 'g')))";
        $map = [];
        foreach (array_chunk($fioKeysLower, 800) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "
                SELECT {$fioSql} AS fio_key,
                  ({$lineSqlExpr}) AS line,
                  vt.\"_lineno3221\" AS ln
                FROM public.{$refTable} c
                INNER JOIN public.{$vtTable} vt ON vt.\"{$vtRefCol}\" = c._idrref
                WHERE NOT COALESCE(c._marked, false)
                  AND {$fioSql} IN ({$in})
                ORDER BY 1, 3 NULLS LAST
            ";
            try {
                $rows = DB::connection($this->connection)->select($sql, $chunk);
            } catch (\Throwable $e) {
                continue;
            }
            $byFio = [];
            foreach ($rows as $r) {
                $fk = (string) ($r->fio_key ?? '');
                if ($fk === '') {
                    continue;
                }
                if (! isset($byFio[$fk])) {
                    $byFio[$fk] = [];
                }
                $byFio[$fk][] = ['line' => (string) ($r->line ?? ''), 'ln' => (int) ($r->ln ?? 0)];
            }
            foreach ($byFio as $fk => $lines) {
                $addr = $this->pickAddressLine($lines);
                if ($addr !== '') {
                    $map[$fk] = $addr;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $cfg  services.lmb_1c_contragent_sync
     * @return array<int, string>
     */
    private function resolveVt3220LineColumns(array $cfg): array
    {
        $raw = trim((string) ($cfg['vt3220_address_line_columns'] ?? ''));
        if ($raw === '') {
            $single = preg_replace('/[^a-z0-9_]/i', '', $cfg['vt3220_address_line_column'] ?? '_fld3224');

            return $single !== '' ? [$single] : ['_fld3224'];
        }
        $cols = [];
        foreach (preg_split('/\s*,\s*/', $raw) as $p) {
            $c = preg_replace('/[^a-z0-9_]/i', '', $p);
            if ($c !== '') {
                $cols[] = $c;
            }
        }

        return $cols !== [] ? array_values(array_unique($cols)) : ['_fld3224'];
    }

    /**
     * Выражение SQL: склеить непустые колонки строки vt3220 (адрес часто не в первой колонке).
     */
    private function buildVt3220LineSqlExpression(array $lineCols): string
    {
        $parts = [];
        foreach ($lineCols as $col) {
            $parts[] = "NULLIF(TRIM(vt.\"{$col}\"::text), '')";
        }

        return 'TRIM(BOTH \' \' FROM CONCAT_WS(\', \', '.implode(', ', $parts).'))';
    }

    /**
     * @param  array<int, array{line: string, ln: int}>  $lines
     */
    private function pickAddressLine(array $lines): string
    {
        foreach ($lines as $item) {
            $line = trim($item['line']);
            if ($line === '' || $this->looksLikePhone($line)) {
                continue;
            }
            if ($this->looksLikeAddress($line)) {
                return $line;
            }
        }
        foreach ($lines as $item) {
            $line = trim($item['line']);
            if ($line === '' || $this->looksLikePhone($line)) {
                continue;
            }
            if (mb_strlen($line) >= 12) {
                return $line;
            }
        }

        return '';
    }

    private function looksLikePhone(string $line): bool
    {
        $d = preg_replace('/\D/', '', $line);

        return strlen($d) >= 10 && strlen($d) <= 12;
    }

    private function looksLikeAddress(string $line): bool
    {
        // «Граница слова» \b в PCRE для кириллицы ненадёжна — ищем подстроки явно.
        $lower = mb_strtolower($line, 'UTF-8');
        $needles = [
            'обл', 'край', 'респ', 'населен', 'г.о.', 'г. ', ' г ', 'ул.', 'улиц', 'просп', 'переул',
            'шоссе', 'дом ', ' д.', ' кв.', 'квартир', 'домовлад', 'строен', 'корпус', 'советск',
        ];
        foreach ($needles as $n) {
            if (mb_strpos($lower, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $fioKeysLower
     * @return array<string, array<string, mixed>>
     */
    private function loadPassportNarrativeMapSimple(
        string $docTable,
        string $docFioCol,
        string $docTextCol,
        string $docDateCol,
        array $fioKeysLower
    ): array {
        $out = [];
        $fkExpr = "lower(trim(regexp_replace(d.\"{$docFioCol}\"::text, '\\s+', ' ', 'g')))";
        foreach (array_chunk($fioKeysLower, 150) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $in = implode(',', array_fill(0, count($chunk), '?'));
            // DISTINCT ON: иначе PostgreSQL отдаёт все строки документов по каждому ФИО → исчерпание памяти при полном синке
            $sql = "
                SELECT DISTINCT ON (fk)
                  fk AS fio_key,
                  trim(d.\"{$docTextCol}\"::text) AS passport_text,
                  d.\"{$docDateCol}\" AS doc_dt
                FROM public.{$docTable} d
                CROSS JOIN LATERAL (SELECT {$fkExpr} AS fk) AS x
                WHERE fk IN ({$in})
                  AND trim(d.\"{$docTextCol}\"::text) ILIKE '%паспорт%'
                ORDER BY fk, d.\"{$docDateCol}\" DESC NULLS LAST
            ";
            try {
                $rows = DB::connection($this->connection)->select($sql, $chunk);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($rows as $r) {
                $fk = (string) ($r->fio_key ?? '');
                if ($fk === '') {
                    continue;
                }
                $text = trim((string) ($r->passport_text ?? ''));
                if ($text === '') {
                    continue;
                }
                $parsed = $this->parsePassportNarrative($text);
                $parsed['lmb_identity_raw_line'] = mb_substr($text, 0, 2000);
                $out[$fk] = $parsed;
            }
            unset($rows);
        }

        return $out;
    }
}
