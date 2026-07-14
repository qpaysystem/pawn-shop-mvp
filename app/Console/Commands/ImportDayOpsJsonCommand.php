<?php

namespace App\Console\Commands;

use App\Services\OneC\DayOpsJsonImportService;
use Illuminate\Console\Command;

/** Импорт JSON-выгрузки опердня 1С в БД портала. */
class ImportDayOpsJsonCommand extends Command
{
    protected $signature = 'lmb:import-day-ops-json
                            {file : Путь к JSON (ДанныеИз1С_*.json)}
                            {--dry-run : Только разбор и счётчики, без записи}
                            {--force : Подтвердить запись в БД}';

    protected $description = 'Импорт опердня 1С: залоги/выкупы, скупка, продажи, ПКО/РКО, банк, события по товару';

    public function handle(DayOpsJsonImportService $service): int
    {
        $file = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force')) {
            $this->error('Для записи укажите --force (или сначала --dry-run).');

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[dry-run] ' : '').'Импорт: '.$file);
        $result = $service->import($file, $dryRun);

        $this->table(['Метрика', 'Значение'], collect($result['stats'])->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->line('Всего документов в файле: '.$result['total']);

        foreach (array_slice($result['warnings'], 0, 20) as $w) {
            $this->warn($w);
        }
        foreach (array_slice($result['errors'], 0, 20) as $e) {
            $this->error($e);
        }

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
