<?php

namespace App\Console\Commands;

use App\Services\LmbUserApiService;
use Illuminate\Console\Command;

class LmbPingCommand extends Command
{
    protected $signature = 'lmb:ping {phone? : Номер телефона для проверки GET .../user/{phone} (например 79998887766)}';

    protected $description = 'Проверить доступность API 1С LMB (вывести причину при ошибке)';

    public function handle(LmbUserApiService $api): int
    {
        $baseUrl = config('services.lmb_user_api.base_url');
        $loginUrl = config('services.lmb_user_api.login_url');
        $phone = $this->argument('phone');

        if ($phone !== null && $phone !== '') {
            $url = $baseUrl . '/user/' . $api->formatPhoneForUserUrl($phone);
            $this->info("Проверка: GET {$url}");
            $result = $api->testUserConnection($phone);
        } else {
            $this->info("Проверка: {$baseUrl}/ostatki");
            $result = $api->testConnection();
        }

        if ($loginUrl) {
            $this->line("Логин (сначала): " . $loginUrl);
        }
        $this->line('');

        if ($result['ok']) {
            $this->info('Ответ: ' . $result['message']);
            if ($result['body_preview'] !== null) {
                $this->line('Тело (начало): ' . $result['body_preview']);
            }
            return self::SUCCESS;
        }

        $this->error('Не удалось подключиться к API.');
        $this->line('Причина: ' . $result['message']);
        if ($result['status'] !== null) {
            $this->line('HTTP-код: ' . $result['status']);
        }
        $this->line('');
        $this->line('Возможные причины:');
        $this->line('  • Сервер 5.128.186.3 недоступен с этой сети (VPN, офисная сеть, хостинг).');
        $this->line('  • Служба 1С (HTTP-сервис LMB) не запущена или путь /lmb/hs не опубликован.');
        $this->line('  • Фаервол блокирует исходящие запросы на хост/порт из LMB_USER_API_URL.');
        $this->line('');
        $this->line('Запустите команду с сервера, с которого доступен API 1С (см. ' . $baseUrl . ').');

        return self::FAILURE;
    }
}
