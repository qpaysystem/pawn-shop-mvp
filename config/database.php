<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'pawn_shop_mvp'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (defined('Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'pawn_shop_mvp'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        // База 1С (MS SQL Server): Srvr + Ref — типичная связка для 1С
        'lmb_1c' => [
            'driver' => 'sqlsrv',
            'host' => env('LMB_DB_HOST', '1c-dl380g7'),
            'port' => env('LMB_DB_PORT', '1433'),
            'database' => env('LMB_DB_DATABASE', 'testlmb'),
            'username' => env('LMB_DB_USERNAME', 'UserWebServis'),
            'password' => env('LMB_DB_PASSWORD', 'UserWebServis'),
            'charset' => env('LMB_DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => env('LMB_DB_ENCRYPT', 'optional'),
            'trust_server_certificate' => env('LMB_DB_TRUST_CERT', true),
        ],

        // База 1С (PostgreSQL): при LMB_DB_DRIVER=pgsql использовать это соединение (хост 192.168.7.250, пользователь lmb)
        'lmb_1c_pgsql' => [
            'driver' => 'pgsql',
            'host' => env('LMB_DB_HOST', '192.168.7.250'),
            'port' => env('LMB_DB_PORT', '5432'),
            'database' => env('LMB_DB_DATABASE', 'testlmb'),
            'username' => env('LMB_DB_USERNAME', 'UserWebServis'),
            'password' => env('LMB_DB_PASSWORD', 'UserWebServis'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
    ],
    'migrations' => 'migrations',
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
