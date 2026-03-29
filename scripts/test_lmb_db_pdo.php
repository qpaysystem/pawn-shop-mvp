#!/usr/bin/env php
<?php

/**
 * Проверка подключения к БД 1С через PDO dblib (FreeTDS).
 * Запуск: php scripts/test_lmb_db_pdo.php
 * Требует: VPN включён, порт 1433 открыт на сервере 1С.
 *
 * Переменные из .env (или задайте в окружении): LMB_DB_HOST, LMB_DB_PORT, LMB_DB_DATABASE, LMB_DB_USERNAME, LMB_DB_PASSWORD.
 */

// Подгрузка .env из корня проекта (если скрипт запускают из корня: php scripts/test_lmb_db_pdo.php)
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

$host = getenv('LMB_DB_HOST') ?: '1c-dl380g7';
$port = getenv('LMB_DB_PORT') ?: '1433';
$database = getenv('LMB_DB_DATABASE') ?: 'testlmb';
$username = getenv('LMB_DB_USERNAME') ?: 'UserWebServis';
$password = getenv('LMB_DB_PASSWORD') ?: 'UserWebServis';

$dsn = "dblib:host={$host}:{$port};dbname={$database}";
echo "Подключение: {$host}:{$port} / {$database}\n";

try {
    $pdo = new PDO($dsn, $username, $password);
    echo "OK. Подключение установлено.\n";
    $stmt = $pdo->query("SELECT TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_SCHEMA, TABLE_NAME");
    $tables = $stmt->fetchAll(PDO::FETCH_OBJ);
    echo 'Таблиц: '.count($tables)."\n";
    foreach (array_slice($tables, 0, 10) as $row) {
        echo '  '.$row->TABLE_SCHEMA.'.'.$row->TABLE_NAME."\n";
    }
    if (count($tables) > 10) {
        echo '  ... и ещё '.(count($tables) - 10)."\n";
    }
} catch (PDOException $e) {
    echo 'Ошибка: '.$e->getMessage()."\n";
    exit(1);
}
