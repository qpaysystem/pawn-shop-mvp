<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Обнаружение таблиц документов 1С (_document*) в PostgreSQL.
 * Помогает найти таблицу документа «Операция по залогу» (ЛМБ_ОперацияПоЗалогу) для настройки LMB_1C_PAWN_DOCUMENT_TABLE.
 */
class LmbDiscover1cDocumentsCommand extends Command
{
    protected $signature = 'lmb:discover-1c-documents
                            {--table= : Показать одну таблицу (имя без префикса public)}
                            {--count : Подсчитать записи (WHERE NOT _marked)}
                            {--limit=50 : Максимум таблиц в списке (0 = все)}
                            {--exclude-known : Исключить известные таблицы (например _document389x1 = скупка)}
                            {--with-vt : Показать табличные части (_document*_vt*) для каждого документа}';

    protected $description = 'Найти таблицы документов 1С (_document*) для настройки синхронизации залогов';

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Команда работает только при LMB_DB_DRIVER=pgsql. Проверьте .env.');

            return self::FAILURE;
        }

        $conn = 'lmb_1c_pgsql';
        try {
            DB::connection($conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения к БД 1С: '.$e->getMessage());

            return self::FAILURE;
        }

        $singleTable = $this->option('table');
        $withCount = $this->option('count');
        $limit = (int) $this->option('limit');
        $excludeKnown = $this->option('exclude-known');
        $withVt = $this->option('with-vt');

        $tablesSql = "
            SELECT c.relname AS table_name
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
              AND c.relname LIKE '_document%'
              AND c.relname NOT LIKE '%\_vt%'
            ORDER BY c.relname
        ";
        $params = [];
        if ($singleTable !== null && $singleTable !== '') {
            $tablesSql = "
                SELECT c.relname AS table_name
                FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relname = ?
            ";
            $params = [$singleTable];
        }

        $tables = DB::connection($conn)->select($tablesSql, $params);
        if ($limit > 0) {
            $tables = array_slice($tables, 0, $limit);
        }

        $knownPurchase = ['_document389', '_document389x1'];
        if ($excludeKnown) {
            $tables = array_filter($tables, function ($r) use ($knownPurchase) {
                foreach ($knownPurchase as $k) {
                    if (strpos($r->table_name, $k) === 0) {
                        return false;
                    }
                }

                return true;
            });
            $tables = array_values($tables);
        }

        if (empty($tables)) {
            $this->warn('Таблицы документов не найдены.');

            return self::SUCCESS;
        }

        $this->info('Таблицы документов 1С (public._document*, без табличных частей _vt):');
        $this->newLine();

        $columnsSql = "
            SELECT a.attname AS column_name
            FROM pg_catalog.pg_attribute a
            WHERE a.attrelid = (
                SELECT c.oid FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relname = ?
            )
              AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attnum
        ";

        foreach ($tables as $row) {
            $name = $row->table_name;
            $this->line('<fg=cyan>public.'.$name.'</>');

            if ($withCount) {
                try {
                    $count = DB::connection($conn)->selectOne(
                        "SELECT COUNT(*) AS c FROM public.{$name} WHERE NOT _marked"
                    );
                    $this->line('  Записей (без пометки): '.($count->c ?? 0));
                } catch (\Throwable $e) {
                    $this->line('  <fg=red>Ошибка подсчёта: '.$e->getMessage().'</>');
                }
            }

            $columns = DB::connection($conn)->select($columnsSql, [$name]);
            $colNames = array_map(fn ($c) => $c->column_name, $columns);
            $hint = $this->hintForDocument($colNames);
            if ($hint !== '') {
                $this->line('  <fg=green>Подсказка: '.$hint.'</>');
            }
            $this->line('  Колонки: '.implode(', ', array_slice($colNames, 0, 20)).(count($colNames) > 20 ? '…' : ''));

            if ($withVt) {
                $base = preg_replace('/x1$/', '', $name);
                $vtSql = "
                    SELECT c.relname AS table_name
                    FROM pg_catalog.pg_class c
                    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = 'public' AND c.relkind = 'r'
                      AND c.relname LIKE ? AND c.relname LIKE '%\_vt%'
                    ORDER BY c.relname
                ";
                $vtTables = DB::connection($conn)->select($vtSql, [$base.'%']);
                foreach ($vtTables as $vt) {
                    $this->line('  <fg=yellow>Табличная часть: public.'.$vt->table_name.'</>');
                }
            }
            $this->newLine();
        }

        $this->line('Для залога (ЛМБ_ОперацияПоЗалогу) ищите таблицу с колонками: дата (_date_time), номер (_number), ссылка на контрагента (_fld*rref), сумма займа, срок. Табличная часть: _document*_vt* (вещи).');
        $this->line('После выбора таблицы задайте в .env: LMB_1C_PAWN_DOCUMENT_TABLE=имя_таблицы и при необходимости LMB_1C_PAWN_TABLE_PART_TABLE=имя_табличной_части. См. docs/LMB_INTEGRATION_FULL.md');

        return self::SUCCESS;
    }

    private function hintForDocument(array $colNames): string
    {
        $hasDate = in_array('_date_time', $colNames) || in_array('_date', $colNames);
        $hasNumber = in_array('_number', $colNames);
        $hasRref = false;
        foreach ($colNames as $c) {
            if (str_ends_with($c, 'rref') && str_starts_with($c, '_fld')) {
                $hasRref = true;
                break;
            }
        }
        if ($hasDate && $hasNumber && $hasRref) {
            return 'похоже на документ с датой, номером и ссылкой (подходит для залога/операции).';
        }

        return '';
    }
}
