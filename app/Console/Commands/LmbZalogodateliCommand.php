<?php

namespace App\Console\Commands;

use App\Services\LmbUserApiService;
use Illuminate\Console\Command;

class LmbZalogodateliCommand extends Command
{
    protected $signature = 'lmb:zalogodateli
                            {--format=table : table|json}
                            {--limit= : Максимум записей (по умолчанию все)}';

    protected $description = 'Список действующих залогодателей с личными и контактными данными (через API 1С или БД)';

    public function handle(LmbUserApiService $api): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $format = $this->option('format') ?: 'table';

        $items = $api->getZalogodateli();

        if ($items === null || $items === []) {
            $this->warn('Метод GET .../zalogodateli в 1С не реализован или вернул пустой ответ.');
            $this->newLine();
            $this->line('Чтобы получить список залогодателей:');
            $this->line('  1) Добавьте в HTTP-сервис 1С метод zalogodateli (GET), возвращающий JSON-массив контрагентов с полями: user_uid, ФИО, phone, email.');
            $this->line('  2) Либо выдайте роли lmb право SELECT на представление/таблицу с залогодателями в БД (см. docs/LMB_ZALOGODATELI_export.md).');
            $this->newLine();
            $this->line('Подробно: docs/LMB_ZALOGODATELI_export.md');

            return self::FAILURE;
        }

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        $rows = $this->normalizeRows($items);

        if ($format === 'json') {
            $this->output->writeln(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if (empty($rows)) {
            $this->info('Записей нет.');

            return self::SUCCESS;
        }

        $this->table(
            array_keys($rows[0]),
            $rows
        );
        $this->info('Всего: '.count($rows));

        return self::SUCCESS;
    }

    /**
     * Привести элементы к единому виду для вывода (ФИО, телефон, email).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, string>>
     */
    private function normalizeRows(array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            $fio = trim(sprintf(
                '%s %s %s',
                (string) ($row['last_name'] ?? $row['lastname'] ?? $row['familiya'] ?? ''),
                (string) ($row['first_name'] ?? $row['firstname'] ?? $row['imya'] ?? ''),
                (string) ($row['second_name'] ?? $row['secondname'] ?? $row['otchestvo'] ?? '')
            ));
            if ($fio === '') {
                $fio = (string) ($row['full_name'] ?? $row['name'] ?? $row['description'] ?? '');
            }
            $out[] = [
                'user_uid' => (string) ($row['user_uid'] ?? $row['id'] ?? $row['guid'] ?? ''),
                'ФИО' => $fio,
                'phone' => (string) ($row['phone'] ?? $row['telephone'] ?? ''),
                'email' => (string) ($row['email'] ?? $row['e_mail'] ?? ''),
            ];
        }

        return $out;
    }
}
