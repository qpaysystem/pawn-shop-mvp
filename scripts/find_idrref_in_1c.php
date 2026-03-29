<?php

/**
 * Поиск таблицы, где _idrref = заданному hex (один запрос на таблицу).
 * Запуск: php scripts/find_idrref_in_1c.php 82f6000c29822b4011ecafeb7e6b5094
 */
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$hex = $argv[1] ?? '';
if (! preg_match('/^[0-9a-f]{32}$/i', $hex)) {
    fwrite(STDERR, "Usage: php find_idrref_in_1c.php <32_hex_chars>\n");
    exit(1);
}

$c = DB::connection('lmb_1c_pgsql');
$tables = $c->select("
    SELECT c.relname AS t
    FROM pg_catalog.pg_class c
    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public' AND c.relkind = 'r'
      AND c.relname LIKE '_reference%'
      AND EXISTS (
        SELECT 1 FROM pg_catalog.pg_attribute a
        WHERE a.attrelid = c.oid AND a.attname = '_idrref' AND a.attnum > 0 AND NOT a.attisdropped
      )
    ORDER BY c.relname
");

foreach ($tables as $row) {
    $t = $row->t;
    try {
        $one = $c->selectOne("SELECT 1 AS o FROM public.\"{$t}\" WHERE _idrref = decode(?, 'hex') LIMIT 1", [$hex]);
        if ($one) {
            echo "FOUND: public.{$t}\n";
        }
    } catch (Throwable $e) {
        // skip
    }
}
echo "Done.\n";
