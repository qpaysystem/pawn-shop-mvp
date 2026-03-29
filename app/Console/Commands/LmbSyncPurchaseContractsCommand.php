<?php

namespace App\Console\Commands;

use App\Services\LmbPurchaseSyncService;
use Illuminate\Console\Command;

/**
 * Синхронизация договоров скупки из БД 1С (_document389x1): создаёт/обновляет purchase_contracts и items.
 */
class LmbSyncPurchaseContractsCommand extends Command
{
    protected $signature = 'lmb:sync-purchase-contracts
                            {--dry-run : Только показать, сколько записей будет обработано, без записи}
                            {--no-create-clients : Не создавать клиентов из 1С, если их ещё нет в базе (только существующие user_uid)}';

    protected $description = 'Синхронизировать договоры скупки и товары из 1С (_document389x1); при необходимости подтягивает контрагентов';

    public function handle(LmbPurchaseSyncService $service): int
    {
        $this->info('Синхронизация договоров скупки из 1С…');

        if ($this->option('no-create-clients')) {
            config(['services.lmb_1c_purchase_sync.create_missing_clients' => false]);
        }

        if ($this->option('dry-run')) {
            $result = $service->sync(function (int $processed, int $total) {
                if ($processed === $total || $processed % 50 === 0) {
                    $this->line("  Обработано: {$processed}/{$total}");
                }
            }, true);
            $this->info('[dry-run] Создано бы: '.$result['created'].', обновлено бы: '.$result['updated'].', пропущено: '.$result['skipped'].' (из них без клиента: '.($result['skipped_no_client'] ?? 0).').');
            $this->comment('  Подсказка: в dry-run клиенты из 1С заранее не создаются — счёт «без клиента» может быть завышен.');
            if (! empty($result['errors'])) {
                foreach (array_slice($result['errors'], 0, 5) as $err) {
                    $this->warn('  '.$err);
                }
            }

            return self::SUCCESS;
        }

        $result = $service->sync(function (int $processed, int $total) {
            if ($processed === $total || $processed % 50 === 0) {
                $this->line("  Обработано: {$processed}/{$total}");
            }
        }, false);

        $this->info("Готово. Создано: {$result['created']}, обновлено: {$result['updated']}, пропущено: {$result['skipped']} (из них без клиента в базе: ".($result['skipped_no_client'] ?? 0).').');

        if (! empty($result['clients_from_1c'])) {
            $c = $result['clients_from_1c'];
            $this->info("Контрагенты из 1С (для скупки): создано {$c['created']}, обновлено {$c['updated']}, пропущено при разборе карточки {$c['skipped']}.");
        }

        if (! empty($result['errors'])) {
            $this->warn('Ошибки:');
            foreach (array_slice($result['errors'], 0, 10) as $err) {
                $this->line('  '.$err);
            }
            if (count($result['errors']) > 10) {
                $this->line('  ... и ещё '.(count($result['errors']) - 10));
            }
        }

        return self::SUCCESS;
    }
}
