<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Item;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use Illuminate\Console\Command;

/**
 * Очистить из БД данные, перенесённые из 1С: договоры залога, договоры скупки, товары, клиенты.
 * Порядок удаления учитывает внешние ключи.
 */
class LmbClearSyncedDataCommand extends Command
{
    protected $signature = 'lmb:clear-synced-data
                            {--dry-run : Показать количество записей, не удалять}
                            {--force : Не спрашивать подтверждение}';

    protected $description = 'Удалить договоры залога, скупки, товары и клиентов из БД (для повторного переноса из 1С)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $countPawn = PawnContract::count();
        $countPurchase = PurchaseContract::count();
        $countItems = Item::count();
        $countClients = Client::count();

        $this->table(
            ['Сущность', 'Записей'],
            [
                ['Договоры залога (pawn_contracts)', $countPawn],
                ['Договоры скупки (purchase_contracts)', $countPurchase],
                ['Товары (items)', $countItems],
                ['Клиенты (clients)', $countClients],
            ]
        );

        if ($countPawn + $countPurchase + $countItems + $countClients === 0) {
            $this->info('Нет данных для удаления.');

            return self::SUCCESS;
        }

        if (! $dryRun && ! $force && ! $this->confirm('Удалить эти записи? Порядок: залоги → скупка → товары → клиенты.', false)) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Режим dry-run: удаление не выполнялось.');

            return self::SUCCESS;
        }

        $this->info('Удаление...');

        $deletedPawn = PawnContract::query()->delete();
        $this->line("  Договоры залога: {$deletedPawn}");

        $deletedPurchase = PurchaseContract::query()->delete();
        $this->line("  Договоры скупки: {$deletedPurchase}");

        $deletedItems = Item::query()->delete();
        $this->line("  Товары: {$deletedItems}");

        $deletedClients = Client::query()->delete();
        $this->line("  Клиенты: {$deletedClients}");

        $this->info('Готово. Можно заново запустить lmb:sync-contragents и lmb:sync-pawn-contracts / lmb:sync-purchase-contracts.');

        return self::SUCCESS;
    }
}
