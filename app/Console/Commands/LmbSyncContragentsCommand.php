<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\LmbContragentSyncService;
use Illuminate\Console\Command;

class LmbSyncContragentsCommand extends Command
{
    protected $signature = 'lmb:sync-contragents
                            {--dry-run : Не записывать в БД, только показать количество записей из 1С}
                            {--only-with-passport : Синхронизировать только контрагентов с заполненными серией/номером паспорта в 1С}
                            {--prune : После синка удалить из БД клиентов из 1С, у которых нет паспорта (оставить только с паспортными данными). Требует --only-with-passport}
                            {--backfill-passports-from-inforeg : После синка дозаполнить passport_data из регистра 1С (inforeg) для клиентов с пустым паспортом}
                            {--backfill-identity-from-1c : После синка дозаполнить вид документа, кем/когда выдан, адрес из 1С (документ 517 + vt3220)}';

    protected $description = 'Синхронизировать контрагентов из БД 1С в таблицу clients (список клиентов в приложении)';

    public function handle(LmbContragentSyncService $sync): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Синхронизация из БД 1С возможна только при LMB_DB_DRIVER=pgsql. Проверьте .env.');

            return self::FAILURE;
        }

        $table = config('services.lmb_1c_contragent_sync.table', '_reference122x1');
        $this->info("Таблица 1С: public.{$table}");

        $onlyWithPassport = $this->option('only-with-passport');
        $prune = $this->option('prune');
        if ($prune && ! $onlyWithPassport) {
            $this->error('Опция --prune требует --only-with-passport.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            try {
                $query = \Illuminate\Support\Facades\DB::connection('lmb_1c_pgsql')
                    ->table("public.{$table}")
                    ->whereRaw('NOT _marked');
                if ($onlyWithPassport) {
                    $ps = preg_replace('/[^a-z0-9_]/i', '', config('services.lmb_1c_contragent_sync.passport_series_column', '_fld3202'));
                    $pn = preg_replace('/[^a-z0-9_]/i', '', config('services.lmb_1c_contragent_sync.passport_number_column', '_fld3201'));
                    $query->whereRaw("(TRIM(COALESCE(\"{$ps}\"::text, '')) != '' OR TRIM(COALESCE(\"{$pn}\"::text, '')) != '')");
                }
                $count = $query->count();
                $this->info($onlyWithPassport
                    ? "Записей с паспортом для синхронизации: {$count}"
                    : "Записей для синхронизации (без пометки на удаление): {$count}");
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        if ($prune) {
            if (! $this->confirm('Удалить из БД всех клиентов из 1С без паспортных данных? В нашей базе останутся только клиенты с паспортом.')) {
                return self::SUCCESS;
            }
        }

        // Полный синк загружает десятки тысяч строк из 1С + preload vt3220/док.517 — при memory_limit=128M часто не хватает
        @ini_set('memory_limit', '512M');

        if ($onlyWithPassport) {
            $this->info('Синхронизация только контрагентов с паспортными данными в 1С...');
        } else {
            $this->info('Синхронизация...');
        }
        $result = $sync->sync(function (int $processed, int $total) {
            $this->output->write("\r  Обработано: {$processed} / {$total}");
        }, $onlyWithPassport);
        $this->newLine();

        $this->table(
            ['Создано', 'Обновлено', 'Пропущено'],
            [[$result['created'], $result['updated'], $result['skipped']]]
        );

        if ($onlyWithPassport && isset($result['synced_uids']) && count($result['synced_uids']) > 0) {
            $this->line('  Синхронизировано контрагентов с паспортом: '.count($result['synced_uids']));
        }

        if ($prune && isset($result['synced_uids'])) {
            $syncedUids = $result['synced_uids'];
            $deleted = Client::whereNotNull('user_uid')
                ->whereNotIn('user_uid', $syncedUids)
                ->delete();
            $this->info("Удалено клиентов из 1С без паспортных данных: {$deleted}. В базе остались только клиенты с паспортом.");
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

        if ($this->option('backfill-passports-from-inforeg') && ! $this->option('dry-run')) {
            $this->newLine();
            $this->info('Дозаполнение паспортов из регистра 1С (inforeg)...');
            $pass = $sync->syncPassportsFromInforegForExistingClients(false, false);
            $this->line("  Обновлено записей: {$pass['updated']}, пропущено (уже есть паспорт): {$pass['skipped_existing_passport']}, нет в регистре: {$pass['not_in_inforeg']}");
        }

        if ($this->option('backfill-identity-from-1c') && ! $this->option('dry-run')) {
            $this->newLine();
            $this->info('Дозаполнение реквизитов удостоверения личности из 1С...');
            $id = $sync->syncIdentityDetailsFrom1cForExistingClients(false);
            $this->line("  Обновлено записей: {$id['updated']}");
        }

        $this->info('Готово. Клиенты из 1С отображаются в разделе «Клиенты».');

        return self::SUCCESS;
    }
}
