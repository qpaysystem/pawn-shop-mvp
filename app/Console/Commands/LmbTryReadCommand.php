<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LmbTryReadCommand extends Command
{
    protected $signature = 'lmb:try-read
                            {--table=_acc38 : Имя таблицы в public (например _acc38)}';

    protected $description = 'Проверить, удаётся ли прочитать данные из таблицы БД 1С (текущий пользователь из .env)';

    public function handle(): int
    {
        $table = $this->option('table');
        if (! preg_match('/^[a-z0-9_]+$/i', $table)) {
            $this->error('Недопустимое имя таблицы.');

            return self::FAILURE;
        }

        $connectionName = env('LMB_DB_DRIVER', 'sqlsrv') === 'pgsql' ? 'lmb_1c_pgsql' : 'lmb_1c';

        $this->info('Подключение: '.config("database.connections.{$connectionName}.host").' / '.config("database.connections.{$connectionName}.database"));
        $this->info('Пользователь: '.config("database.connections.{$connectionName}.username"));
        $this->line('');

        try {
            $sample = DB::connection($connectionName)->table($table)->limit(1)->first();
            $this->info("Чтение таблицы public.{$table}: OK.");

            if ($sample) {
                $this->line('Пример полей: '.implode(', ', array_keys((array) $sample)));
            } else {
                $this->line('Таблица пуста.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка: '.$e->getMessage());
            $this->line('');
            $this->warn('Чтобы дать права на чтение: см. docs/LMB_ZALOGODATELI_export.md и scripts/grant_lmb_select.sql');
            $this->warn('Либо подключитесь под другим пользователем (задайте LMB_DB_USERNAME / LMB_DB_PASSWORD в .env).');

            return self::FAILURE;
        }
    }
}
