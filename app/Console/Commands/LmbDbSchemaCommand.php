<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LmbDbSchemaCommand extends Command
{
    protected $signature = 'lmb:db-schema
                            {--table= : Показать только одну таблицу (имя или Schema.Name)}
                            {--limit=50 : Максимум таблиц в списке (0 = без ограничения)}';

    protected $description = 'Подключиться к БД 1С (lmb_1c) и вывести структуру: список таблиц и колонок';

    public function handle(): int
    {
        $connectionName = env('LMB_DB_DRIVER', 'sqlsrv') === 'pgsql' ? 'lmb_1c_pgsql' : 'lmb_1c';

        $this->info('Подключение к БД 1С: '.config("database.connections.{$connectionName}.host").' / '.config("database.connections.{$connectionName}.database"));
        $this->newLine();

        try {
            $pdo = DB::connection($connectionName)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения: '.$e->getMessage());
            $this->newLine();
            $this->warn('Для MS SQL: нужен драйвер pdo_sqlsrv. Для PostgreSQL: задайте LMB_DB_DRIVER=pgsql, LMB_DB_HOST и LMB_DB_PORT=5432 в .env.');

            return self::FAILURE;
        }

        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->info("Драйвер: {$driver}");
        $this->newLine();

        $singleTable = $this->option('table');
        $limit = (int) $this->option('limit');

        if ($driver === 'sqlsrv') {
            return $this->describeSqlServer($connectionName, $singleTable, $limit);
        }

        if ($driver === 'pgsql') {
            return $this->describePostgres($connectionName, $singleTable, $limit);
        }

        $this->warn("Драйвер {$driver} не поддерживается этой командой. Поддерживаются: sqlsrv, pgsql.");

        return self::FAILURE;
    }

    private function describeSqlServer(string $conn, ?string $singleTable, int $limit): int
    {
        $tablesSql = "
            SELECT TABLE_SCHEMA, TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_SCHEMA, TABLE_NAME
        ";
        $params = [];
        if ($singleTable !== null && $singleTable !== '') {
            if (str_contains($singleTable, '.')) {
                [$schema, $name] = explode('.', $singleTable, 2);
                $tablesSql = "
                    SELECT TABLE_SCHEMA, TABLE_NAME
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_TYPE = 'BASE TABLE'
                      AND TABLE_SCHEMA = ?
                      AND TABLE_NAME = ?
                ";
                $params = [$schema, $name];
            } else {
                $tablesSql = "
                    SELECT TABLE_SCHEMA, TABLE_NAME
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_TYPE = 'BASE TABLE'
                      AND TABLE_NAME = ?
                    ORDER BY TABLE_SCHEMA, TABLE_NAME
                ";
                $params = [$singleTable];
            }
        }

        $tables = DB::connection($conn)->select($tablesSql, $params);
        if ($limit > 0) {
            $tables = array_slice($tables, 0, $limit);
        }

        if (empty($tables)) {
            $this->warn('Таблицы не найдены.');

            return self::SUCCESS;
        }

        $this->info('Таблицы: '.count($tables));
        $this->newLine();

        $columnsSql = '
            SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ';

        foreach ($tables as $row) {
            $schema = $row->TABLE_SCHEMA;
            $tableName = $row->TABLE_NAME;
            $fullName = $schema.'.'.$tableName;
            $this->line('<fg=cyan>'.$fullName.'</>');
            $columns = DB::connection($conn)->select($columnsSql, [$schema, $tableName]);
            foreach ($columns as $col) {
                $len = $col->CHARACTER_MAXIMUM_LENGTH !== null ? "({$col->CHARACTER_MAXIMUM_LENGTH})" : '';
                $nullable = $col->IS_NULLABLE === 'YES' ? ' NULL' : '';
                $this->line('  '.$col->COLUMN_NAME.' '.$col->DATA_TYPE.$len.$nullable);
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function describePostgres(string $conn, ?string $singleTable, int $limit): int
    {
        // pg_class: видим все таблицы (1С и др.), даже если information_schema их не показывает из-за прав
        $tablesSql = "
            SELECT n.nspname AS table_schema, c.relname AS table_name
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
              AND c.relkind = 'r'
            ORDER BY n.nspname, c.relname
        ";
        $params = [];
        if ($singleTable !== null && $singleTable !== '') {
            if (str_contains($singleTable, '.')) {
                [$schema, $name] = explode('.', $singleTable, 2);
                $tablesSql = "
                    SELECT n.nspname AS table_schema, c.relname AS table_name
                    FROM pg_catalog.pg_class c
                    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = ? AND c.relname = ? AND c.relkind = 'r'
                ";
                $params = [$schema, $name];
            } else {
                $tablesSql = "
                    SELECT n.nspname AS table_schema, c.relname AS table_name
                    FROM pg_catalog.pg_class c
                    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
                      AND c.relkind = 'r' AND c.relname = ?
                    ORDER BY n.nspname, c.relname
                ";
                $params = [$singleTable];
            }
        }

        $tables = DB::connection($conn)->select($tablesSql, $params);
        if ($limit > 0) {
            $tables = array_slice($tables, 0, $limit);
        }

        // В части баз 1С документы хранятся в _documentNNN без суффикса x1; при запросе _document31784x1 пробуем _document31784
        if (empty($tables) && $singleTable !== null && $singleTable !== '' && ! str_contains($singleTable, '.')) {
            if (preg_match('/^(.+)x1$/i', $singleTable, $m)) {
                $params = [$m[1]];
                $tables = DB::connection($conn)->select($tablesSql, $params);
                if (! empty($tables)) {
                    $this->warn("Таблицы «{$singleTable}» нет; показана структура «{$m[1]}» (в этой базе документы без суффикса x1).");
                }
            }
        }

        if (empty($tables)) {
            $this->warn('Таблицы не найдены.');

            return self::SUCCESS;
        }

        $this->info('Таблицы: '.count($tables));
        $this->newLine();

        // Колонки через pg_attribute — видим структуру независимо от прав на information_schema
        $columnsSql = '
            SELECT a.attname AS column_name,
                   pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type,
                   NOT a.attnotnull AS is_nullable
            FROM pg_catalog.pg_attribute a
            WHERE a.attrelid = (
                SELECT c.oid FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ? AND c.relname = ?
            )
              AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attnum
        ';

        foreach ($tables as $row) {
            $schema = $row->table_schema;
            $tableName = $row->table_name;
            $fullName = $schema.'.'.$tableName;
            $this->line('<fg=cyan>'.$fullName.'</>');
            $columns = DB::connection($conn)->select($columnsSql, [$schema, $tableName]);
            foreach ($columns as $col) {
                $nullable = $col->is_nullable ? ' NULL' : '';
                $this->line('  '.$col->column_name.' '.$col->data_type.$nullable);
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
