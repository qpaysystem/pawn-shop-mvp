<?php

/**
 * Поиск строки 961613 по ВСЕМ строковым колонкам одной таблицы одним запросом (OR по колонкам).
 * Запуск: php scripts/search_passport_1c.php [table_name]
 * Без аргумента — перебирает только public._reference122x1 и пару других.
 */

require dirname(__DIR__).'/vendor/autoload.php';
$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$conn = 'lmb_1c_pgsql';
$search = $argv[2] ?? '961613';

$tables = $argv[1] ?? null;
if ($tables === null) {
    $tables = ['public._reference122x1', 'public._reference122'];
} else {
    $tables = [strpos($tables, '.') ? $tables : 'public.'.$tables];
}

$columnsSql = '
    SELECT a.attname AS column_name, pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
    FROM pg_catalog.pg_attribute a
    WHERE a.attrelid = (SELECT c.oid FROM pg_catalog.pg_class c
                        JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                        WHERE n.nspname = ? AND c.relname = ?)
      AND a.attnum > 0 AND NOT a.attisdropped
    ORDER BY a.attnum
';

foreach ((array) $tables as $fullTable) {
    $parts = explode('.', $fullTable, 2);
    $schema = $parts[0] ?? 'public';
    $table = $parts[1] ?? $parts[0];

    $columns = \Illuminate\Support\Facades\DB::connection($conn)->select($columnsSql, [$schema, $table]);
    $stringCols = [];
    foreach ($columns as $col) {
        $t = strtolower($col->data_type);
        if (str_contains($t, 'char') || str_contains($t, 'text') || str_contains($t, 'varchar') || str_contains($t, 'mchar') || str_contains($t, 'mvarchar')) {
            if (preg_match('/^[a-zA-Z0-9_]+$/', $col->column_name)) {
                $stringCols[] = $col->column_name;
            }
        }
    }

    if (empty($stringCols)) {
        echo "{$fullTable}: нет строковых колонок\n";

        continue;
    }

    $conditions = [];
    foreach ($stringCols as $c) {
        $conditions[] = '"'.str_replace('"', '""', $c).'"::text LIKE \'%'.addslashes($search).'%\'';
    }
    $where = implode(' OR ', $conditions);
    $quotedTable = '"'.str_replace('"', '""', $schema).'"."'.str_replace('"', '""', $table).'"';
    $sql = "SELECT COUNT(*) AS c FROM {$quotedTable} WHERE ({$where})";

    try {
        $r = \Illuminate\Support\Facades\DB::connection($conn)->selectOne($sql);
        $cnt = (int) $r->c;
        if ($cnt > 0) {
            echo "НАЙДЕНО {$fullTable}: {$cnt} записей с «{$search}»\n";
            // Узнать в какой колонке
            foreach ($stringCols as $c) {
                $qc = '"'.str_replace('"', '""', $c).'"';
                $q = \Illuminate\Support\Facades\DB::connection($conn)->selectOne(
                    "SELECT COUNT(*) AS c FROM {$quotedTable} WHERE {$qc}::text LIKE ?",
                    ['%'.$search.'%']
                );
                if ((int) $q->c > 0) {
                    echo "  колонка: {$c}\n";
                }
            }
        }
    } catch (\Throwable $e) {
        echo "{$fullTable}: ошибка ".$e->getMessage()."\n";
    }
}
