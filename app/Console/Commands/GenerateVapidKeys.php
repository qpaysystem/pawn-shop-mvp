<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    protected $signature = 'pwa:vapid';
    protected $description = 'Сгенерировать VAPID-ключи для push-уведомлений (добавьте в .env)';

    public function handle(): int
    {
        if (!class_exists(\Minishlink\WebPush\VAPID::class)) {
            $this->error('Установите пакет: composer require minishlink/web-push');
            return self::FAILURE;
        }

        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        $this->line('Добавьте в .env:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->newLine();
        $this->info('Готово. После добавления выполните: php artisan config:clear');
        return self::SUCCESS;
    }
}
