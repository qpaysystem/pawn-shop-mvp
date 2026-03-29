<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Обнаружение регистров накопления (остатки) в БД 1С: список таблиц _accumrg*, колонки, количество записей.
 */
class Lmb1cBalancesDiscoveryCommand extends Command
{
    protected $signature = 'lmb:1c-balances-discovery
                            {--table= : Только одна таблица (например _accumrg26200)}
                            {--count : Подсчитать записи в каждой таблице (медленно)}
                            {--limit=30 : Максимум таблиц в выводе (0 = все)}';

    protected $description = 'Найти регистры накопления в 1С (_accumrg*) и вывести структуру';

    public function handle(): int
    {
        $conn = config('database.default');
        if (env('LMB_DB_DRIVER') === 'pgsql') {
            $conn = 'lmb_1c_pgsql';
        }
        try {
            DB::connection($conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения к БД 1С: '.$e->getMessage());

            return self::FAILURE;
        }

        $singleTable = $this->option('table');
        $withCount = $this->option('count');
        $limit = (int) $this->option('limit');

        $sql = "
            SELECT n.nspname AS table_schema, c.relname AS table_name
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
              AND c.relname LIKE '_accumrg%'
            ORDER BY c.relname
        ";
        $params = [];
        if ($singleTable !== null && $singleTable !== '') {
            $sql = "
                SELECT n.nspname AS table_schema, c.relname AS table_name
                FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relname = ?
            ";
            $params = [$singleTable];
        }

        $tables = DB::connection($conn)->select($sql, $params);
        if ($limit > 0) {
            $tables = array_slice($tables, 0, $limit);
        }

        if (empty($tables)) {
            $this->warn('Регистры накопления (_accumrg*) не найдены.');

            return self::SUCCESS;
        }

        $this->info('Регистры накопления (остатки/обороты) в 1С: '.count($tables).' таблиц.');
        $this->newLine();

        $columnsSql = '
            SELECT a.attname AS column_name, pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
            FROM pg_catalog.pg_attribute a
            WHERE a.attrelid = (SELECT c.oid FROM pg_catalog.pg_class c
                               JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                               WHERE n.nspname = ? AND c.relname = ?)
              AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attnum
        ';

        $rows = [];
        foreach ($tables as $row) {
            $tableName = $row->table_name;
            $columns = DB::connection($conn)->select($columnsSql, [$row->table_schema, $tableName]);
            $colList = array_map(fn ($c) => $c->column_name, $columns);

            $countStr = '—';
            if ($withCount) {
                try {
                    $count = DB::connection($conn)->selectOne("SELECT COUNT(*) AS c FROM public.{$tableName}");
                    $countStr = (string) ($count->c ?? 0);
                } catch (\Throwable $e) {
                    $countStr = 'ошибка';
                }
            }

            $rows[] = [$tableName, implode(', ', array_slice($colList, 0, 8)).(count($colList) > 8 ? '…' : ''), $countStr];
            $this->line('<fg=cyan>'.$tableName.'</>');
            $this->line('  Колонки: '.implode(', ', $colList));
            if ($withCount) {
                $this->line('  Записей: '.$countStr);
            }
            $this->newLine();
        }

        if ($withCount && count($rows) > 1) {
            $this->table(['Таблица', 'Колонки (первые)', 'Записей'], $rows);
        }

        $this->info('Для синхронизации остатков укажите в конфиге таблицу регистра и колонки (см. docs/LMB_1C_BALANCES_SYNC.md).');

        return self::SUCCESS;
    }
}
