<?php

namespace App\Console\Commands;

use App\Services\Lmb1cBalancesSyncService;
use Illuminate\Console\Command;

/**
 * Синхронизировать остатки из регистра накопления 1С в таблицу lmb_register_balances.
 */
class LmbSync1cBalancesCommand extends Command
{
    protected $signature = 'lmb:sync-1c-balances
                            {--dry-run : Только показать, что будет прочитано из 1С}';

    protected $description = 'Синхронизировать остатки из регистра накопления 1С (_accumrg*) в нашу базу';

    public function handle(Lmb1cBalancesSyncService $service): int
    {
        $this->info('Синхронизация остатков из 1С…');

        if ($this->option('dry-run')) {
            $cfg = config('services.lmb_1c_balances_sync', []);
            $table = $cfg['register_table'] ?? '';
            if ($table === '') {
                $this->warn('Не задан LMB_1C_BALANCES_REGISTER_TABLE. Запустите lmb:1c-balances-discovery --count, укажите таблицу и колонки в .env.');

                return self::SUCCESS;
            }
            $this->line("  Регистр: {$table}");
            $this->line('  Измерения: '.implode(', ', $cfg['dimension_columns'] ?? []));
            $this->line('  Количество: '.($cfg['quantity_column'] ?? '—'));
            $this->line('  Сумма: '.($cfg['amount_column'] ?? '—'));

            return self::SUCCESS;
        }

        $result = $service->sync(function (int $processed, int $total) {
            if ($processed % 500 === 0 || $processed === $total) {
                $this->line("  Обработано записей: {$processed}/{$total}");
            }
        });

        $this->info("Готово. Прочитано записей: {$result['rows_read']}, остатков записано: {$result['balances_count']}.");

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $this->warn('  '.$err);
            }
        }

        return self::SUCCESS;
    }
}
