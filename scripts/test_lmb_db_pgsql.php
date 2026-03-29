#!/usr/bin/env php
<?php

/**
 * Проверка подключения к БД 1С (PostgreSQL).
 * Запуск: php scripts/test_lmb_db_pgsql.php
 * Требует: VPN включён, порт 5432 доступен на сервере 1С.
 *
 * Переменные из .env (или задайте в окружении): LMB_DB_HOST, LMB_DB_PORT, LMB_DB_DATABASE, LMB_DB_USERNAME, LMB_DB_PASSWORD.
 */
$envFile = __DIR__.'/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
            putenv(trim($m[1]).'='.trim($m[2], " \t\"'"));
        }
    }
}

$host = getenv('LMB_DB_HOST') ?: '192.168.7.250';
$port = getenv('LMB_DB_PORT') ?: '5432';
$database = getenv('LMB_DB_DATABASE') ?: 'lmb';
$username = getenv('LMB_DB_USERNAME') ?: 'lmb';
$password = getenv('LMB_DB_PASSWORD') ?: 'lmb';

$dsn = "pgsql:host={$host};port={$port};dbname={$database}";
echo "Подключение: {$host}:{$port} / {$database}\n";

try {
    $pdo = new PDO($dsn, $username, $password);
    echo "OK. Подключение установлено.\n";
    $stmt = $pdo->query("
        SELECT table_schema, table_name
        FROM information_schema.tables
        WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
          AND table_type = 'BASE TABLE'
        ORDER BY table_schema, table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_OBJ);
    echo 'Таблиц: '.count($tables)."\n";
    foreach (array_slice($tables, 0, 15) as $row) {
        echo '  '.$row->table_schema.'.'.$row->table_name."\n";
    }
    if (count($tables) > 15) {
        echo '  ... и ещё '.(count($tables) - 15)."\n";
    }
} catch (PDOException $e) {
    echo 'Ошибка: '.$e->getMessage()."\n";
    exit(1);
}
