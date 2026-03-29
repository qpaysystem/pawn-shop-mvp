<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Выгружает все паспортные данные из найденных при поиске таблиц 1С:
 * - _reference122x1 (серия/номер _fld3201/_fld3202, кем выдан _fld3197)
 * - _reference122_vt3220x1 (хранилище значений типа «Документ удостоверяющий личность»)
 */
class LmbExportPassportFrom1cCommand extends Command
{
    protected $signature = 'lmb:export-passport-from-1c
                            {--uid= : UID контрагента (hex) — показать данные по одному клиенту}
                            {--limit=0 : Макс. записей (0 = все)}
                            {--csv= : Путь к CSV-файлу для выгрузки}
                            {--source=both : Источник: ref122, vt3220, both}
                            {--with-issued-by : Только контрагенты, у которых есть «кем выдан» (МВД/УВД/выдан) в _fld3197 или в хранилище vt3220}
                            {--list= : Показать N контрагентов из 1С с данными документа (серия/номер/кем выдан из _reference122x1)}';

    protected $description = 'Выгрузить паспортные данные из таблиц 1С (результаты поиска выдан/МВД)';

    private string $conn = 'lmb_1c_pgsql';

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только для LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        try {
            DB::connection($this->conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Подключение: '.$e->getMessage());

            return self::FAILURE;
        }

        $uid = $this->option('uid');
        $limit = (int) $this->option('limit');
        $csvPath = $this->option('csv');
        $source = $this->option('source');
        $withIssuedBy = $this->option('with-issued-by');

        if ($uid !== null && $uid !== '') {
            return $this->showOneClient($uid, $source);
        }

        if ($withIssuedBy) {
            return $this->showWithIssuedBy($limit ?: 100, $csvPath);
        }

        $listN = $this->option('list');
        if ($listN !== null && $listN !== '') {
            return $this->listContragentsWithPassportData((int) $listN ?: 100, $csvPath);
        }

        $rows = [];

        if ($source === 'both' || $source === 'ref122') {
            $this->info('Выгрузка из _reference122x1 (серия, номер, кем выдан)...');
            $fromRef = $this->fetchFromReference122x1($limit, null);
            foreach ($fromRef as $r) {
                $rows[] = array_merge(['source' => 'ref122'], (array) $r);
            }
            $this->line('  Записей: '.count($fromRef));
        }

        if ($source === 'both' || $source === 'vt3220') {
            $this->info('Выгрузка из _reference122_vt3220x1 (хранилище паспорта)...');
            $fromVt = $this->fetchFromVt3220($limit, null);
            foreach ($fromVt as $r) {
                $rows[] = array_merge(['source' => 'vt3220'], (array) $r);
            }
            $this->line('  Записей: '.count($fromVt));
        }

        if (empty($rows)) {
            $this->warn('Нет данных для выгрузки.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Всего записей с паспортными данными: '.count($rows));

        $refRows = array_values(array_filter($rows, fn ($r) => ($r['source'] ?? '') === 'ref122'));
        $vtRows = array_values(array_filter($rows, fn ($r) => ($r['source'] ?? '') === 'vt3220'));

        if (! empty($refRows)) {
            $this->line('<comment>Из _reference122x1 (серия/номер/кем выдан):</comment>');
            $this->table(array_keys($refRows[0]), array_slice($refRows, 0, 15));
            if (count($refRows) > 15) {
                $this->line('... и ещё '.(count($refRows) - 15).' записей.');
            }
            $this->newLine();
        }
        if (! empty($vtRows)) {
            $this->line('<comment>Из _reference122_vt3220x1 (хранилище паспорта):</comment>');
            $this->table(array_keys($vtRows[0]), array_slice($vtRows, 0, 15));
            if (count($vtRows) > 15) {
                $this->line('... и ещё '.(count($vtRows) - 15).' записей.');
            }
        }

        if ($csvPath !== null && $csvPath !== '') {
            $this->writeCsv($csvPath, $rows);
            $this->info('CSV сохранён: '.$csvPath);
        }

        return self::SUCCESS;
    }

    private function showOneClient(string $uid, string $source): int
    {
        $uidHex = str_replace([' ', '-'], '', strtolower($uid));
        if (strlen($uidHex) !== 32 || ! ctype_xdigit($uidHex)) {
            $this->error('UID должен быть 32 символа hex (например 812a0050569bbedf11eaa55bd5c03527).');

            return self::FAILURE;
        }

        $this->info('Клиент UID: '.$uidHex);
        $this->newLine();

        if ($source === 'both' || $source === 'ref122') {
            $fromRef = $this->fetchFromReference122x1(1, $uidHex);
            if (empty($fromRef)) {
                $this->warn('В _reference122x1 запись не найдена.');
            } else {
                $r = (array) $fromRef[0];
                $this->line('<comment>Карточка контрагента (_reference122x1)</comment>');
                $labels = [
                    'uid' => 'UID',
                    'name' => 'ФИО',
                    'phone' => 'Телефон',
                    'passport_series' => 'Серия паспорта',
                    'passport_number' => 'Номер паспорта',
                    'passport_series_alt' => 'Серия (альт.)',
                    'passport_number_alt' => 'Номер (альт.)',
                    'issued_by' => 'Кем выдан',
                    'birth_date' => 'Дата рождения',
                ];
                foreach ($labels as $key => $label) {
                    $v = $r[$key] ?? '';
                    if ((string) $v !== '') {
                        $this->line("  <info>{$label}:</info> ".(strlen((string) $v) > 120 ? substr((string) $v, 0, 120).'…' : $v));
                    }
                }
                $this->newLine();
            }
        }

        if ($source === 'both' || $source === 'vt3220') {
            $fromVt = $this->fetchFromVt3220(100, $uidHex);
            if (empty($fromVt)) {
                $this->line('<comment>В _reference122_vt3220x1 записей по этому контрагенту нет.</comment>');
            } else {
                $this->line('<comment>Хранилище значений (_reference122_vt3220x1) — записей: '.count($fromVt).'</comment>');
                foreach ($fromVt as $i => $row) {
                    $row = (array) $row;
                    $this->line('  — запись '.($i + 1).':');
                    foreach ($row as $k => $v) {
                        if ($k === 'source') {
                            continue;
                        }
                        $v = trim((string) $v);
                        if ($v === '') {
                            continue;
                        }
                        $display = strlen($v) > 100 ? substr($v, 0, 100).'…' : $v;
                        $this->line('    '.$k.': '.$display);
                    }
                    $this->newLine();
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Список контрагентов из 1С с данными документа удостоверяющего личность (серия/номер/кем выдан, когда выдан, вид документа).
     */
    private function listContragentsWithPassportData(int $limit, ?string $csvPath): int
    {
        $syncConfig = config('services.lmb_1c_contragent_sync', []);
        $dateIssuedCol = $syncConfig['date_issued_column'] ?? '';
        $docTypeCol = $syncConfig['document_type_column'] ?? '';
        $ownerCol = $this->getVt3220OwnerColumn();

        $extraSelect = [];
        if ($dateIssuedCol !== '') {
            $q = '"'.str_replace('"', '""', $dateIssuedCol).'"';
            $extraSelect[] = "c.{$q}::text AS date_issued";
        } else {
            $extraSelect[] = 'NULL::text AS date_issued';
        }
        if ($docTypeCol !== '') {
            $q = '"'.str_replace('"', '""', $docTypeCol).'"';
            $extraSelect[] = "trim(c.{$q}::text) AS document_type_ref";
        } else {
            $extraSelect[] = 'NULL::text AS document_type_ref';
        }
        // Вид документа из хранилища vt3220: короткая строка (часто «Паспорт гражданина РФ») — берём кратчайшую из _fld3224 в пределах 120 символов
        if ($ownerCol !== '') {
            $ownerQuoted = '"'.str_replace('"', '""', $ownerCol).'"';
            $extraSelect[] = "(SELECT trim(vt.\"_fld3224\"::text) FROM public._reference122_vt3220x1 vt WHERE vt.{$ownerQuoted} = c._idrref AND length(trim(COALESCE(vt.\"_fld3224\"::text,''))) BETWEEN 1 AND 120 ORDER BY length(trim(vt.\"_fld3224\"::text)) LIMIT 1) AS document_type_vt";
        } else {
            $extraSelect[] = 'NULL::text AS document_type_vt';
        }

        $sql = "
            SELECT
                encode(c._idrref, 'hex') AS uid,
                trim(c.\"_fld3178\"::text) AS name,
                trim(c.\"_fld41084\"::text) AS phone,
                trim(c.\"_fld3197\"::text) AS issued_by,
                trim(c.\"_fld3202\"::text) AS ps,
                trim(c.\"_fld3201\"::text) AS pn,
                trim(c.\"_fld3184\"::text) AS ps_alt,
                trim(c.\"_fld3185\"::text) AS pn_alt,
                ".implode(', ', $extraSelect)."
            FROM public._reference122x1 c
            WHERE NOT COALESCE(c._marked, false)
            AND (
                TRIM(COALESCE(c.\"_fld3202\"::text, '')) != ''
                OR TRIM(COALESCE(c.\"_fld3201\"::text, '')) != ''
                OR TRIM(COALESCE(c.\"_fld3184\"::text, '')) != ''
                OR TRIM(COALESCE(c.\"_fld3185\"::text, '')) != ''
                OR TRIM(COALESCE(c.\"_fld3197\"::text, '')) != ''
            )
            ORDER BY (CASE WHEN TRIM(COALESCE(c.\"_fld3201\"::text, '')) != '' OR TRIM(COALESCE(c.\"_fld3185\"::text, '')) != '' THEN 0 ELSE 1 END), c.\"_fld3178\"::text
            LIMIT ".(int) $limit;

        try {
            $rows = DB::connection($this->conn)->select($sql);
        } catch (\Throwable $e) {
            $this->error('Ошибка: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Контрагенты из 1С с паспортными данными (кем/когда выдан, вид документа): '.count($rows));
        $this->newLine();

        if (empty($rows)) {
            $this->warn('Записей не найдено.');

            return self::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $r) {
            $series = trim($r->ps ?? '') ?: trim($r->ps_alt ?? '');
            $number = trim($r->pn ?? '') ?: trim($r->pn_alt ?? '');
            $issued = trim($r->issued_by ?? '');
            if (strlen($issued) > 50) {
                $issued = substr($issued, 0, 50).'…';
            }
            $dateIssued = isset($r->date_issued) ? trim($r->date_issued) : '';
            $docTypeRef = isset($r->document_type_ref) ? trim($r->document_type_ref) : '';
            $docTypeVt = isset($r->document_type_vt) ? trim($r->document_type_vt) : '';
            $docType = $docTypeRef !== '' ? $docTypeRef : $docTypeVt;
            if (strlen($docType) > 40) {
                $docType = substr($docType, 0, 40).'…';
            }
            $tableRows[] = [
                'uid' => $r->uid,
                'name' => $r->name ?? '',
                'phone' => $r->phone ?? '',
                'issued_by' => $issued,
                'date_issued' => $dateIssued,
                'document_type' => $docType,
                'series' => $series,
                'number' => $number,
            ];
        }

        $headers = ['uid', 'ФИО', 'phone', 'кем выдан', 'когда выдан', 'вид документа', 'серия', 'номер'];
        $this->table($headers, array_map(fn ($r) => [$r['uid'], $r['name'], $r['phone'], $r['issued_by'], $r['date_issued'], $r['document_type'], $r['series'], $r['number']], $tableRows));

        if ($csvPath !== null && $csvPath !== '') {
            $fp = fopen($csvPath, 'w');
            if ($fp) {
                fputcsv($fp, ['uid', 'name', 'phone', 'issued_by', 'date_issued', 'document_type', 'passport_series', 'passport_number'], ';');
                foreach ($tableRows as $r) {
                    fputcsv($fp, array_values($r), ';');
                }
                fclose($fp);
                $this->info('CSV: '.$csvPath);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Контрагенты, у которых есть «кем выдан»: непустой _fld3197 или запись в vt3220 с МВД/УВД/выдан.
     */
    private function showWithIssuedBy(int $limit, ?string $csvPath): int
    {
        $ownerCol = $this->getVt3220OwnerColumn();
        $vtCondition = '';
        if ($ownerCol !== '') {
            $ownerQuoted = '"'.str_replace('"', '""', $ownerCol).'"';
            $vtCondition = " OR c._idrref IN (
                SELECT vt.{$ownerQuoted} FROM public._reference122_vt3220x1 vt
                WHERE vt.\"_fld3224\"::text ILIKE '%МВД%' OR vt.\"_fld3224\"::text ILIKE '%УВД%' OR vt.\"_fld3224\"::text ILIKE '%выдан%'
                   OR vt.\"_fld3225\"::text ILIKE '%МВД%' OR vt.\"_fld3225\"::text ILIKE '%УВД%' OR vt.\"_fld3227\"::text ILIKE '%МВД%'
                   OR vt.\"_fld34009\"::text ILIKE '%МВД%' OR vt.\"_fld34009\"::text ILIKE '%выдан%'
            )";
        }

        $sql = "
            SELECT
                encode(c._idrref, 'hex') AS uid,
                trim(c.\"_fld3178\"::text) AS name,
                trim(c.\"_fld41084\"::text) AS phone,
                trim(c.\"_fld3197\"::text) AS issued_by,
                trim(c.\"_fld3202\"::text) AS passport_series,
                trim(c.\"_fld3201\"::text) AS passport_number,
                trim(c.\"_fld3184\"::text) AS passport_series_alt,
                trim(c.\"_fld3185\"::text) AS passport_number_alt
            FROM public._reference122x1 c
            WHERE NOT COALESCE(c._marked, false)
            AND (
                TRIM(COALESCE(c.\"_fld3197\"::text, '')) != ''
                {$vtCondition}
            )
            ORDER BY c.\"_fld3178\"::text
            LIMIT ".(int) $limit;

        try {
            $rows = DB::connection($this->conn)->select($sql);
        } catch (\Throwable $e) {
            $this->error('Ошибка: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Контрагенты из 1С с заполненным «кем выдан» (_fld3197 или МВД/УВД/выдан в хранилище): '.count($rows));
        $this->newLine();

        if (empty($rows)) {
            $this->warn('Таких записей нет.');

            return self::SUCCESS;
        }

        $tableRows = [];
        foreach ($rows as $r) {
            $series = trim($r->passport_series ?? '') ?: trim($r->passport_series_alt ?? '');
            $number = trim($r->passport_number ?? '') ?: trim($r->passport_number_alt ?? '');
            $issued = trim($r->issued_by ?? '');
            if (strlen($issued) > 80) {
                $issued = substr($issued, 0, 80).'…';
            }
            $tableRows[] = [
                'uid' => $r->uid,
                'name' => $r->name ?? '',
                'phone' => $r->phone ?? '',
                'issued_by' => $issued,
                'series' => $series,
                'number' => $number,
            ];
        }

        $this->table(['uid', 'ФИО', 'phone', 'кем выдан', 'серия', 'номер'], array_map(function ($r) {
            return [$r['uid'], $r['name'], $r['phone'], $r['issued_by'], $r['series'], $r['number']];
        }, $tableRows));

        if ($csvPath !== null && $csvPath !== '') {
            $fp = fopen($csvPath, 'w');
            if ($fp) {
                fputcsv($fp, ['uid', 'name', 'phone', 'issued_by', 'passport_series', 'passport_number'], ';');
                foreach ($tableRows as $r) {
                    fputcsv($fp, array_values($r), ';');
                }
                fclose($fp);
                $this->info('CSV: '.$csvPath);
            }
        }

        return self::SUCCESS;
    }

    private function fetchFromReference122x1(int $limit, ?string $uidHex): array
    {
        $sql = "
            SELECT
                encode(c._idrref, 'hex') AS uid,
                c.\"_fld3178\"::text AS name,
                c.\"_fld41084\"::text AS phone,
                trim(c.\"_fld3202\"::text) AS passport_series,
                trim(c.\"_fld3201\"::text) AS passport_number,
                trim(c.\"_fld3184\"::text) AS passport_series_alt,
                trim(c.\"_fld3185\"::text) AS passport_number_alt,
                trim(c.\"_fld3197\"::text) AS issued_by,
                c.\"_fld3191\"::text AS birth_date
            FROM public._reference122x1 c
            WHERE NOT COALESCE(c._marked, false)
        ";
        $params = [];
        if ($uidHex !== null) {
            $sql .= " AND c._idrref = decode(?, 'hex')";
            $params[] = $uidHex;
        } else {
            $sql .= ' AND (
                (c."_fld3197" IS NOT NULL AND length(trim(c."_fld3197"::text)) > 0)
                OR (c."_fld3201" IS NOT NULL AND length(trim(c."_fld3201"::text)) > 0)
                OR (c."_fld3202" IS NOT NULL AND length(trim(c."_fld3202"::text)) > 0)
                OR (c."_fld3184" IS NOT NULL AND length(trim(c."_fld3184"::text)) > 0)
                OR (c."_fld3185" IS NOT NULL AND length(trim(c."_fld3185"::text)) > 0)
            )';
        }
        $sql .= ' ORDER BY c."_fld3178"::text';
        if ($limit > 0) {
            $sql .= ' LIMIT '.(int) $limit;
        }

        return $params ? DB::connection($this->conn)->select($sql, $params) : DB::connection($this->conn)->select($sql);
    }

    private function fetchFromVt3220(int $limit, ?string $uidHex): array
    {
        $ownerCol = $this->getVt3220OwnerColumn();
        if ($ownerCol === '') {
            $this->warn('  Не найдена колонка-ссылка на контрагента в _reference122_vt3220x1.');

            return [];
        }
        $cols = $this->getVt3220StringColumns();
        if (empty($cols)) {
            $this->warn('  Таблица _reference122_vt3220x1 не найдена или нет строковых колонок.');

            return [];
        }

        $selectVt = array_map(function ($c) {
            return 'vt."'.str_replace('"', '""', $c).'"::text AS vt_'.$c;
        }, $cols);
        $ownerQuoted = '"'.str_replace('"', '""', $ownerCol).'"';
        $selectList = "encode(vt.{$ownerQuoted}, 'hex') AS contragent_uid, x1.\"_fld3178\"::text AS name, ".implode(', ', $selectVt);

        $sql = "
            SELECT {$selectList}
            FROM public._reference122_vt3220x1 vt
            JOIN public._reference122x1 x1 ON x1._idrref = vt.{$ownerQuoted}
        ";
        $params = [];
        if ($uidHex !== null) {
            $sql .= " WHERE vt.{$ownerQuoted} = decode(?, 'hex')";
            $params[] = $uidHex;
        }
        $sql .= ' ORDER BY x1."_fld3178"::text';
        if ($limit > 0) {
            $sql .= ' LIMIT '.(int) $limit;
        }

        try {
            return $params ? DB::connection($this->conn)->select($sql, $params) : DB::connection($this->conn)->select($sql);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'column') && str_contains($e->getMessage(), 'does not exist')) {
                $this->warn('  Проверьте схему таблицы (колонка владельца).');
            }
            $this->warn('  Ошибка: '.$e->getMessage());

            return [];
        }
    }

    private function getVt3220OwnerColumn(): string
    {
        $sql = "
            SELECT a.attname AS column_name
            FROM pg_catalog.pg_attribute a
            WHERE a.attrelid = (SELECT c.oid FROM pg_catalog.pg_class c
                               JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                               WHERE n.nspname = 'public' AND c.relname = '_reference122_vt3220x1')
              AND a.attnum > 0 AND NOT a.attisdropped
              AND (a.attname = '_reference122_idrref' OR (a.attname LIKE '%122%' AND a.attname LIKE '%rref'))
            ORDER BY CASE WHEN a.attname = '_reference122_idrref' THEN 0 ELSE 1 END, a.attnum
            LIMIT 1
        ";
        try {
            $row = DB::connection($this->conn)->selectOne($sql);

            return $row ? $row->column_name : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function getVt3220StringColumns(): array
    {
        $typeSql = "
            SELECT a.attname AS column_name, pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
            FROM pg_catalog.pg_attribute a
            WHERE a.attrelid = (SELECT c.oid FROM pg_catalog.pg_class c
                               JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                               WHERE n.nspname = 'public' AND c.relname = '_reference122_vt3220x1')
              AND a.attnum > 0 AND NOT a.attisdropped
              AND a.attname NOT LIKE '%_idrref' AND a.attname NOT LIKE '%_rref'
            ORDER BY a.attnum
        ";
        $columns = DB::connection($this->conn)->select($typeSql);
        $stringTypes = ['character varying', 'varchar', 'text', 'mvarchar', 'mchar', 'char'];
        $out = [];
        foreach ($columns as $col) {
            $type = strtolower($col->data_type);
            foreach ($stringTypes as $st) {
                if (str_contains($type, $st)) {
                    $out[] = $col->column_name;
                    break;
                }
            }
        }

        return $out;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $fp = fopen($path, 'w');
        if (! $fp) {
            $this->error('Не удалось создать файл: '.$path);

            return;
        }
        $headers = array_keys($rows[0]);
        fputcsv($fp, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($fp, array_values($row), ';');
        }
        fclose($fp);
    }
}
