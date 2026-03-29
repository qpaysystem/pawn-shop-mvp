<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Синхронизация филиалов/подразделений из справочника 1С в таблицу stores.
 * Заполняет lmb_store_uid для маппинга при переносе залогов и скупки.
 * --only-used: только те склады, что встречаются в документах залога и скупки (хранение товара).
 * --clear: перед синхронизацией удалить все магазины, перепривязав зависимости к первому «используемому» магазину.
 */
class LmbSyncStoresFrom1cCommand extends Command
{
    protected $signature = 'lmb:sync-stores-from-1c
                            {--dry-run : Не записывать в БД}
                            {--force : Без подтверждения}
                            {--only-used : Только склады из документов залога и скупки (хранение товара)}
                            {--clear : Очистить все магазины и оставить только используемые в 1С}';

    protected $description = 'Перенести филиалы из справочника 1С (_reference197) в stores';

    /** Таблицы и колонки для перепривязки store_id при --clear */
    private const STORE_FK_TABLES = [
        'pawn_contracts' => ['store_id'],
        'purchase_contracts' => ['store_id'],
        'items' => ['store_id'],
        'client_visits' => ['store_id'],
        'cash_documents' => ['store_id', 'target_store_id'],
        'users' => ['store_id'],
        'ledger_entries' => ['store_id'],
        'expenses' => ['store_id'],
        'bank_accounts' => ['store_id'],
        'employees' => ['store_id'],
        'call_center_contacts' => ['store_id'],
        'storage_locations' => ['store_id'],
        'lmb_register_balances' => ['store_id'],
        'finance_payroll' => ['store_id'],
        'finance_expenses' => ['store_id'],
        'finance_bank' => ['store_id'],
    ];

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только при LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        $cfg = config('services.lmb_1c_stores_sync', []);
        $table = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['table'] ?? '_reference197'));
        $nameCol = $this->sanitizeColumn($cfg['name_column'] ?? '_description');
        $addressCol = $this->sanitizeColumn($cfg['address_column'] ?? '');

        if ($table === '') {
            $this->error('Не задана таблица справочника филиалов. Укажите LMB_1C_STORES_REFERENCE_TABLE в .env (например _reference197).');

            return self::FAILURE;
        }

        try {
            DB::connection('lmb_1c_pgsql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения к БД 1С: '.$e->getMessage());

            return self::FAILURE;
        }

        $onlyUsed = $this->option('only-used');
        $clear = $this->option('clear');
        $usedUids = [];

        if ($onlyUsed || $clear) {
            $usedUids = $this->getUsedStoreUidsFrom1c();
            if (empty($usedUids)) {
                $this->warn('В документах 1С (залог/скупка) не найдено ни одного склада. Проверьте LMB_1C_PAWN_STORE_COLUMN и LMB_1C_PURCHASE_STORE_COLUMN.');
                if ($clear) {
                    return self::FAILURE;
                }
            } else {
                $this->info('Складов в документах 1С (хранение товара): '.count($usedUids).' — '.implode(', ', array_slice($usedUids, 0, 10)).(count($usedUids) > 10 ? '...' : ''));
            }
        }

        $select = "encode(_idrref, 'hex') AS uid, \"{$nameCol}\" AS name";
        if ($addressCol !== '') {
            $select .= ", \"{$addressCol}\" AS address";
        }
        $sql = "SELECT {$select} FROM public.{$table} WHERE NOT _marked";
        $bindings = [];
        if (($onlyUsed || $clear) && ! empty($usedUids)) {
            $placeholders = implode(',', array_fill(0, count($usedUids), '?'));
            $sql = "SELECT {$select} FROM public.{$table} WHERE NOT _marked AND lower(encode(_idrref, 'hex')) IN ({$placeholders})";
            $bindings = array_map('strtolower', $usedUids);
        }

        try {
            $rows = ($onlyUsed || $clear) && empty($usedUids)
                ? []
                : DB::connection('lmb_1c_pgsql')->select($sql, $bindings);
        } catch (\Throwable $e) {
            $this->error('Ошибка чтения справочника: '.$e->getMessage());
            $this->line("Проверьте наличие таблицы {$table} и колонки {$nameCol} (php artisan lmb:db-schema --table={$table}).");

            return self::FAILURE;
        }

        if (empty($rows)) {
            if (($onlyUsed || $clear) && ! empty($usedUids)) {
                // Склады из документов могут относиться к другому справочнику 1С (не _reference197). Создаём магазины по UID с условными именами.
                $rows = array_map(fn ($uid) => (object) [
                    'uid' => $uid,
                    'name' => 'Склад '.substr($uid, 0, 8),
                    'address' => null,
                ], $usedUids);
                $this->warn("Справочник {$table} не содержит этих складов (в 1С склад может быть из другого справочника). Будут созданы записи с условными именами.");
            } else {
                $this->warn($onlyUsed || $clear ? 'Нет записей в справочнике для выбранных складов.' : "В справочнике {$table} нет записей.");

                return self::SUCCESS;
            }
        }

        $this->info('Филиалов к синхронизации: '.count($rows));
        $this->table(
            ['uid', 'name', 'address'],
            array_map(fn ($r) => [$r->uid ?? '', mb_substr($r->name ?? '', 0, 40), mb_substr($r->address ?? '', 0, 30)], array_slice($rows, 0, 15))
        );
        if (count($rows) > 15) {
            $this->line('... и ещё '.(count($rows) - 15));
        }

        if ($this->option('dry-run')) {
            $this->info('Режим dry-run: запись в БД не выполнялась.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Создать/обновить магазины (stores) по этим данным?', false)) {
            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        foreach ($rows as $r) {
            $uid = strtolower((string) ($r->uid ?? ''));
            $name = trim((string) ($r->name ?? ''));
            $address = isset($r->address) ? trim((string) $r->address) : null;
            if ($name === '') {
                $name = 'Филиал '.substr($uid, 0, 8);
            }
            $store = Store::where('lmb_store_uid', $uid)->first();
            if ($store) {
                $store->name = $name;
                if ($address !== null && $address !== '') {
                    $store->address = $address;
                }
                $store->save();
                $updated++;
            } else {
                Store::create([
                    'lmb_store_uid' => $uid,
                    'name' => $name,
                    'address' => $address,
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        if ($clear && ! $this->option('dry-run')) {
            $usedUidsLower = array_map('strtolower', $usedUids);
            $firstUsedStore = Store::whereIn('lmb_store_uid', $usedUidsLower)->orderBy('id')->first();
            $idsToRemove = Store::whereNotIn('lmb_store_uid', $usedUidsLower)->pluck('id')->all();
            if (! empty($idsToRemove) && $firstUsedStore !== null) {
                if (! $this->option('force') && ! $this->confirm('Перепривязать все записи с '.count($idsToRemove).' удаляемых магазинов к магазину «'.$firstUsedStore->name.'» и удалить их?', false)) {
                    return self::SUCCESS;
                }
                $this->reassignStoreFk($idsToRemove, $firstUsedStore->id);
                Store::whereIn('id', $idsToRemove)->delete();
                $this->info('Удалено магазинов: '.count($idsToRemove));
            }
        }

        $this->info("Готово: создано {$created}, обновлено {$updated}. Маппинг по lmb_store_uid используется при lmb:sync-pawn-contracts и lmb:sync-purchase.");

        return self::SUCCESS;
    }

    /** Собрать уникальные UID складов из документов залога и скупки в 1С */
    private function getUsedStoreUidsFrom1c(): array
    {
        $conn = 'lmb_1c_pgsql';
        $uids = [];

        $pawnCfg = config('services.lmb_1c_pawn_sync', []);
        $pawnTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($pawnCfg['document_table'] ?? ''));
        $pawnStoreCol = $this->sanitizeColumn($pawnCfg['store_column'] ?? '');
        if ($pawnTable !== '' && $pawnStoreCol !== '') {
            try {
                $sql = "SELECT DISTINCT lower(encode(\"{$pawnStoreCol}\", 'hex')) AS uid FROM public.{$pawnTable} WHERE NOT _marked AND \"{$pawnStoreCol}\" IS NOT NULL AND \"{$pawnStoreCol}\" != '\\x00000000000000000000000000000000'::bytea";
                $rows = DB::connection($conn)->select($sql);
                foreach ($rows as $r) {
                    if (! empty($r->uid)) {
                        $uids[$r->uid] = true;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('Залог (склад): '.$e->getMessage());
            }
        }

        $purchaseCfg = config('services.lmb_1c_purchase_sync', []);
        $purchaseTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($purchaseCfg['document_table'] ?? ''));
        $purchaseStoreCol = $this->sanitizeColumn($purchaseCfg['store_column'] ?? '');
        if ($purchaseTable !== '' && $purchaseStoreCol !== '') {
            try {
                $sql = "SELECT DISTINCT lower(encode(\"{$purchaseStoreCol}\", 'hex')) AS uid FROM public.{$purchaseTable} WHERE NOT _marked AND \"{$purchaseStoreCol}\" IS NOT NULL AND \"{$purchaseStoreCol}\" != '\\x00000000000000000000000000000000'::bytea";
                $rows = DB::connection($conn)->select($sql);
                foreach ($rows as $r) {
                    if (! empty($r->uid)) {
                        $uids[$r->uid] = true;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('Скупка (склад): '.$e->getMessage());
            }
        }

        return array_keys($uids);
    }

    /** Перепривязать store_id и target_store_id к первому используемому магазину перед удалением */
    private function reassignStoreFk(array $storeIdsToRemove, int $newStoreId): void
    {
        foreach (self::STORE_FK_TABLES as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            foreach ($columns as $col) {
                if (! Schema::hasColumn($tableName, $col)) {
                    continue;
                }
                $updated = DB::table($tableName)->whereIn($col, $storeIdsToRemove)->update([$col => $newStoreId]);
                if ($updated > 0) {
                    $this->line("  {$tableName}.{$col}: перепривязано {$updated} записей.");
                }
            }
        }
    }

    private function sanitizeColumn(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/i', '', $name);
    }
}
