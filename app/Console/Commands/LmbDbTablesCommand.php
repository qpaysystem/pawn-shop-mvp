<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LmbDbTablesCommand extends Command
{
    protected $signature = 'lmb:db-tables
                            {--prefix= : Фильтр по имени таблицы (например _reference или _document)}
                            {--no-vt : Исключить табличные части (_vt)}
                            {--count : Показать количество записей (WHERE NOT _marked) — медленно на больших таблицах}
                            {--limit=200 : Максимум таблиц в выводе (0 = без ограничения)}';

    protected $description = 'Список таблиц БД 1С по префиксу (для изучения структуры)';

    public function handle(): int
    {
        $connectionName = env('LMB_DB_DRIVER', 'sqlsrv') === 'pgsql' ? 'lmb_1c_pgsql' : 'lmb_1c';

        try {
            DB::connection($connectionName)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения: '.$e->getMessage());

            return self::FAILURE;
        }

        $driver = DB::connection($connectionName)->getDriverName();
        if ($driver !== 'pgsql') {
            $this->warn('Команда поддерживает только PostgreSQL. Задайте LMB_DB_DRIVER=pgsql в .env.');

            return self::FAILURE;
        }

        $prefix = $this->option('prefix');
        $noVt = $this->option('no-vt');
        $withCount = $this->option('count');
        $limit = (int) $this->option('limit');

        $sql = "
            SELECT c.relname AS table_name
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
        ";
        $params = [];

        if ($prefix !== null && $prefix !== '') {
            $sql .= ' AND c.relname LIKE ?';
            $params[] = $prefix.'%';
        }

        if ($noVt) {
            $sql .= " AND c.relname NOT LIKE '%_vt%'";
        }

        $sql .= ' ORDER BY c.relname';

        $tables = DB::connection($connectionName)->select($sql, $params);

        if ($limit > 0) {
            $tables = array_slice($tables, 0, $limit);
        }

        if (empty($tables)) {
            $this->info('Таблицы не найдены.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($tables as $row) {
            $name = $row->table_name;
            if ($withCount) {
                try {
                    $count = DB::connection($connectionName)->table($name)->whereRaw('NOT _marked')->count();
                    $rows[] = [$name, $count];
                } catch (\Throwable $e) {
                    $rows[] = [$name, '—'];
                }
            } else {
                $rows[] = [$name];
            }
        }

        if ($withCount) {
            $this->table(['Таблица', 'Записей (NOT _marked)'], $rows);
        } else {
            foreach ($rows as $r) {
                $this->line($r[0]);
            }
        }

        $this->newLine();
        $this->info('Всего: '.count($rows).' таблиц.');

        return self::SUCCESS;
    }
}
