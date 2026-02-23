<?php

namespace App\Console\Commands;

use App\Services\LmbUserApiService;
use Illuminate\Console\Command;

class LmbUserCommand extends Command
{
    protected $signature = 'lmb:user {phone : Номер телефона (ID контрагента), например 79139122194}';

    protected $description = 'Получить данные контрагента из API 1С LMB по номеру телефона (код, имя, телефон)';

    public function handle(LmbUserApiService $api): int
    {
        $phone = $this->argument('phone');
        $normalized = $api->normalizePhoneForId($phone);
        if ($normalized === '') {
            $this->error('Укажите номер телефона (цифры).');
            return self::FAILURE;
        }

        $this->info("Запрос: {$api->normalizePhoneForId($phone)} → " . config('services.lmb_user_api.base_url') . '/user/' . $normalized);

        $data = $api->getUserByPhone($phone);

        if ($data === null) {
            $this->error('Не удалось получить ответ от API (таймаут или ошибка сервера).');
            $this->line('Проверьте доступность ' . config('services.lmb_user_api.base_url') . ' с этого сервера.');
            return self::FAILURE;
        }

        if (isset($data['raw'])) {
            $this->warn('Ответ в нераспознанном формате:');
            $this->line($data['raw']);
            return self::SUCCESS;
        }

        $this->info('Данные контрагента (1С):');
        $this->table(
            ['Поле', 'Значение'],
            [
                ['user_uid (код в 1С)', $data['user_uid'] ?? $data['code'] ?? '—'],
                ['first_name (фамилия/ФИО)', $data['first_name'] ?? $data['name'] ?? '—'],
                ['second_name (имя)', $data['second_name'] ?? '—'],
                ['last_name (отчество)', $data['last_name'] ?? '—'],
                ['phone', $data['phone'] ?? '—'],
            ]
        );

        return self::SUCCESS;
    }
}
