<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Показать все таблицы и колонки БД 1С, связанные с типом «Документ удостоверяющий личность»
 * (в 1С это составной тип, в БД — хранилища значений vt3220 и ссылки на них).
 */
class LmbDocIdentityTablesCommand extends Command
{
    protected $signature = 'lmb:doc-identity-tables
                            {--schema : Показать полную структуру (колонки) каждой найденной таблицы}';

    protected $description = 'Таблицы и колонки 1С, связанные с «Документ удостоверяющий личность» (тип 3220)';

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

        $this->info('Поиск таблиц и колонок, связанных с «Документ удостоверяющий личность» (тип 3220 в 1С)');
        $this->newLine();

        // 1) Таблицы, в имени которых есть 3220 (хранилища значений типа 3220)
        $tablesWith3220 = DB::connection($this->conn)->select("
            SELECT n.nspname AS schema_name, c.relname AS table_name
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
              AND c.relkind = 'r'
              AND c.relname LIKE '%3220%'
            ORDER BY n.nspname, c.relname
        ");

        $this->line('<fg=cyan>Таблицы с «3220» в имени (хранилища значений типа «Документ удостоверяющий личность»):</>');
        if (empty($tablesWith3220)) {
            $this->warn('  Не найдено.');
        } else {
            foreach ($tablesWith3220 as $row) {
                $this->line('  '.$row->schema_name.'.'.$row->table_name);
            }
        }
        $this->newLine();

        // 2) Колонки во всей БД, в имени которых есть 3220 (ссылки на тип или поля внутри vt)
        $columnsWith3220 = DB::connection($this->conn)->select("
            SELECT n.nspname AS schema_name, c.relname AS table_name, a.attname AS column_name,
                   pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_catalog.pg_attribute a ON a.attrelid = c.oid
            WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
              AND c.relkind = 'r'
              AND a.attnum > 0 AND NOT a.attisdropped
              AND a.attname LIKE '%3220%'
            ORDER BY n.nspname, c.relname, a.attnum
        ");

        $this->line('<fg=cyan>Колонки с «3220» в имени (ссылки на тип или поля хранилища):</>');
        if (empty($columnsWith3220)) {
            $this->warn('  Не найдено.');
        } else {
            $grouped = [];
            foreach ($columnsWith3220 as $row) {
                $key = $row->schema_name.'.'.$row->table_name;
                if (! isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $row->column_name.' ('.$row->data_type.')';
            }
            foreach ($grouped as $table => $cols) {
                $this->line('  <fg=yellow>'.$table.'</>');
                foreach ($cols as $col) {
                    $this->line('    '.$col);
                }
            }
        }
        $this->newLine();

        $showSchema = $this->option('schema');
        if ($showSchema && ! empty($tablesWith3220)) {
            $this->line('<fg=cyan>Структура таблиц (колонки):</>');
            $columnsSql = '
                SELECT a.attname AS column_name, pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
                FROM pg_catalog.pg_attribute a
                WHERE a.attrelid = (SELECT c.oid FROM pg_catalog.pg_class c
                    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = ? AND c.relname = ?)
                  AND a.attnum > 0 AND NOT a.attisdropped
                ORDER BY a.attnum
            ';
            foreach ($tablesWith3220 as $row) {
                $this->newLine();
                $this->line('<fg=yellow>'.$row->schema_name.'.'.$row->table_name.'</>');
                $cols = DB::connection($this->conn)->select($columnsSql, [$row->schema_name, $row->table_name]);
                foreach ($cols as $col) {
                    $this->line('  '.$col->column_name.'  '.$col->data_type);
                }
            }
        }

        return self::SUCCESS;
    }
}
