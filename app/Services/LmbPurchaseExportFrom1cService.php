<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Выгрузка документов «Скупка ценностей» из БД 1С: покупатель, товар, реквизиты операции, ссылки на вложения.
 */
class LmbPurchaseExportFrom1cService
{
    private string $connection = 'lmb_1c_pgsql';

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRows(Carbon $from, ?Carbon $to = null): array
    {
        $purchase = config('services.lmb_1c_purchase_sync', []);
        $export = config('services.lmb_1c_purchase_export', []);
        $contr = config('services.lmb_1c_contragent_sync', []);
        $stores = config('services.lmb_1c_stores_sync', []);

        $docTable = $this->safeIdent($purchase['document_table'] ?? '');
        if ($docTable === '') {
            throw new \InvalidArgumentException('Не задана таблица документа скупки (lmb_1c_purchase_sync.document_table).');
        }

        $contrTable = $this->safeIdent($contr['table'] ?? '_reference122x1');
        $storeTable = $this->safeIdent($stores['table'] ?? '_reference197');
        $nomTable = $this->safeIdent($export['nomenclature_table'] ?? '_reference45');
        $attTable = $this->safeIdent($export['attachment_table'] ?? '_reference367x1');
        $branchAltTable = $this->safeIdent($export['branch_alt_table'] ?? '_reference201x1');
        $branchAltRefCol = $this->safeIdent($export['branch_alt_ref_column'] ?? '_fld9644rref');

        $contrCol = $this->safeIdent($purchase['contragent_column'] ?? '_fld9626rref');
        $dateCol = $this->safeIdent($purchase['date_column'] ?? '_date_time');
        $numberCol = $this->safeIdent($purchase['number_column'] ?? '_number');
        $amountCol = $this->safeIdent($purchase['amount_column'] ?? '_fld9631');
        $storeCol = $this->safeIdent($purchase['store_column'] ?? '_fld9627rref');
        $nomRefCol = $this->safeIdent($export['nomenclature_ref_column'] ?? '_fld9632rref');
        $attCol = $this->safeIdent($export['attachment_ref_column'] ?? '_fld46109rref');
        $att2Col = $this->safeIdent($export['attachment2_ref_column'] ?? '_fld46110rref');
        $respCol = $this->safeIdent($export['responsible_ref_column'] ?? '_fld9620rref');

        $phoneCol = $this->safeIdent($contr['phone_column'] ?? '_fld41084');
        $nameCol = $this->safeIdent($contr['name_column'] ?? '_fld3178');
        $passSer = $this->safeIdent($contr['passport_series_column'] ?? '_fld3202');
        $passNum = $this->safeIdent($contr['passport_number_column'] ?? '_fld3201');
        $storeNameCol = $this->safeIdent($stores['name_column'] ?? '_description');

        $nameCols = $purchase['name_columns'] ?? ['_fld9638', '_fld9643', '_fld9650'];
        $nameSelect = [];
        foreach ($nameCols as $col) {
            $c = $this->safeIdent($col);
            if ($c !== '') {
                $alias = 'item_text_'.preg_replace('/[^a-z0-9]/i', '_', $c);
                $nameSelect[] = "d.\"{$c}\" AS \"{$alias}\"";
            }
        }
        $nameSelectSql = $nameSelect !== [] ? implode(', ', $nameSelect).', ' : '';

        $toEnd = $to ? $to->copy()->endOfDay() : null;
        $dateUpper = '';
        $bindings = [$from->format('Y-m-d H:i:s')];
        if ($toEnd !== null) {
            $dateUpper = " AND d.\"{$dateCol}\" <= ?";
            $bindings[] = $toEnd->format('Y-m-d H:i:s');
        }

        $sql = "
            SELECT
                encode(d._idrref, 'hex') AS doc_uid,
                d.\"{$dateCol}\" AS doc_date_time,
                d.\"{$numberCol}\"::text AS doc_number,
                d._posted AS doc_posted,
                d.\"{$amountCol}\" AS doc_amount,
                {$nameSelectSql}
                d._fld9633 AS item_numeric_9633,
                d._fld9634 AS item_numeric_9634,
                d._fld9647::text AS item_code_9647,
                d._fld9639 AS item_date_9639,
                d._fld9646 AS item_date_9646,
                encode(d._fld9623rref, 'hex') AS ref_organization_hex,
                encode(d._fld9624rref, 'hex') AS ref_9624_hex,
                encode(d._fld9644rref, 'hex') AS ref_9644_hex,
                encode(d.\"{$respCol}\", 'hex') AS responsible_ref_hex,
                encode(d.\"{$nomRefCol}\", 'hex') AS nomenclature_uid_hex,
                nom._code::text AS nom_code,
                nom._description::text AS nom_description_short,
                nom._fld1699::text AS nom_fld1699,
                nom._fld1702::text AS nom_fld1702,
                nom._fld1703::text AS nom_fld1703,
                encode(ca._idrref, 'hex') AS contragent_uid_hex,
                ca._description::text AS contragent_card_description,
                ca.\"{$nameCol}\"::text AS contragent_fio,
                ca.\"{$phoneCol}\"::text AS contragent_phone,
                ca.\"{$passSer}\"::text AS contragent_passport_series,
                ca.\"{$passNum}\"::text AS contragent_passport_number,
                encode(st._idrref, 'hex') AS store_uid_hex,
                st.\"{$storeNameCol}\"::text AS store_branch_name,
                br._description::text AS branch_alt_description,
                br._fld5347::text AS branch_alt_comment,
                encode(d.\"{$attCol}\", 'hex') AS attachment_1_uid_hex,
                att1._description::text AS attachment_1_description,
                att1._folder AS attachment_1_is_folder,
                att1._fld41359::text AS attachment_1_fld41359,
                att1._fld41360::text AS attachment_1_fld41360,
                att1._fld41361::text AS attachment_1_fld41361,
                att1._fld41362::text AS attachment_1_fld41362,
                encode(d.\"{$att2Col}\", 'hex') AS attachment_2_uid_hex,
                att2._description::text AS attachment_2_description,
                att2._folder AS attachment_2_is_folder
            FROM public.{$docTable} d
            LEFT JOIN public.{$contrTable} ca ON ca._idrref = d.\"{$contrCol}\" AND NOT ca._marked
            LEFT JOIN public.{$storeTable} st ON st._idrref = d.\"{$storeCol}\" AND NOT st._marked
            LEFT JOIN public.{$branchAltTable} br ON br._idrref = d.\"{$branchAltRefCol}\" AND NOT br._marked
            LEFT JOIN public.{$nomTable} nom ON nom._idrref = d.\"{$nomRefCol}\" AND NOT nom._marked
            LEFT JOIN public.{$attTable} att1 ON att1._idrref = d.\"{$attCol}\" AND NOT att1._marked
            LEFT JOIN public.{$attTable} att2 ON att2._idrref = d.\"{$att2Col}\" AND NOT att2._marked
            WHERE NOT d._marked
              AND d.\"{$dateCol}\" >= ?{$dateUpper}
            ORDER BY d.\"{$dateCol}\", d.\"{$numberCol}\"
        ";

        $rows = DB::connection($this->connection)->select($sql, $bindings);

        return array_map(function ($row) {
            $a = (array) $row;
            foreach ($a as $k => $v) {
                if (is_string($v)) {
                    $t = trim($v, " \t\n\r\0\x0B\xC2\xA0");
                    $a[$k] = $t;
                }
                if (is_string($a[$k] ?? null) && str_ends_with((string) $k, '_hex')
                    && preg_match('/^0+$/', (string) $a[$k])) {
                    $a[$k] = null;
                }
            }

            return $a;
        }, $rows);
    }

    private function safeIdent(string $name): string
    {
        $s = preg_replace('/[^a-z0-9_]/i', '', $name);

        return $s ?? '';
    }
}
