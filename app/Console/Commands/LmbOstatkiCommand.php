<?php

namespace App\Console\Commands;

use App\Services\LmbUserApiService;
use Illuminate\Console\Command;

class LmbOstatkiCommand extends Command
{
    protected $signature = 'lmb:ostatki
                            {--json : Вывести сырой JSON ответа}';

    protected $description = 'Выгрузить каталог товаров (остатки) из API 1С LMB (GET /ostatki)';

    public function handle(LmbUserApiService $api): int
    {
        $baseUrl = config('services.lmb_user_api.base_url');
        $this->info("Запрос: {$baseUrl}/ostatki");

        $data = $api->getOstatki();

        if ($data === null) {
            $this->error('Не удалось получить ответ от API (таймаут или ошибка сервера).');
            $this->line('Проверьте доступность ' . $baseUrl . ' с этого сервера.');
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        if (array_is_list($data)) {
            $this->info('Получено записей: ' . count($data));
            if (count($data) > 0 && is_array($data[0])) {
                $this->table(array_keys($data[0]), array_slice($data, 0, 10));
                if (count($data) > 10) {
                    $this->line('... и ещё ' . (count($data) - 10) . '. Используйте --json для полного вывода.');
                }
            }
        } else {
            $this->info('Ответ (структура):');
            $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }
}
