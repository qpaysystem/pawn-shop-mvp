<?php

namespace App\Console\Commands;

use App\Services\InventoryImportService;
use Illuminate\Console\Command;

/**
 * Импорт из файла инвентаризации (XLS/XLSX): магазины (Горки 1, Мичурина, …), клиенты, товары, договоры залога и скупки.
 * Структура: заголовок строка 4, колонки по docs/LMB_1C_INVENTORY_MAPPING.md.
 */
class LmbImportInventoryCommand extends Command
{
    protected $signature = 'lmb:import-inventory
                            {file : Путь к файлу инвентаризации (Инвентарка.xls или .xlsx)}
                            {--dry-run : Только подсчёт, без записи в БД}
                            {--force : Без подтверждения}
                            {--sheet=0 : Индекс листа (0-based)}
                            {--header-row=4 : Номер строки заголовка}';

    protected $description = 'Импорт из файла инвентаризации: магазины, клиенты, товары, залоги и скупки';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_string($path) || $path === '') {
            $this->error('Укажите путь к файлу: php artisan lmb:import-inventory /path/to/Инвентарка.xls');

            return self::FAILURE;
        }

        $path = realpath($path) ?: $path;
        if (! is_file($path)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        $headerRow = (int) $this->option('header-row');
        $sheetIndex = (int) $this->option('sheet');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('Режим dry-run: в БД ничего не записывается.');
        } else {
            if (! $force && ! $this->confirm('Создать магазины, клиентов, товары и договоры из файла?', false)) {
                return self::SUCCESS;
            }
        }

        $service = new InventoryImportService($headerRow, $sheetIndex);
        $result = $service->import($path, $dryRun);

        foreach ($result['errors'] as $err) {
            $this->error($err);
        }

        $stats = $result['stats'];
        $this->info('Итог:');
        $this->table(
            ['Сущность', 'Создано / Пропущено'],
            [
                ['Магазины', $stats['stores_created']],
                ['Клиенты', $stats['clients_created']],
                ['Товары', $stats['items_created']],
                ['Договоры залога', $stats['pawn_created']],
                ['Договоры скупки', $stats['purchase_created']],
                ['Пропущено строк', $stats['skipped']],
            ]
        );

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
