<?php

namespace App\Console\Commands;

use App\Services\LmbContragentSyncService;
use Illuminate\Console\Command;

/**
 * Дозаполняет passport_data из регистра 1С (_inforg25994 и др.), если в справочнике контрагентов серия/номер пустые.
 */
class LmbSyncPassportsFrom1cCommand extends Command
{
    protected $signature = 'lmb:sync-passports-from-1c
                            {--dry-run : Не записывать в БД}
                            {--force : Перезаписать passport_data даже если уже заполнен}
                            {--with-identity : После паспортов дозаполнить вид документа, кем/когда выдан, адрес (vt3220 + документ 517)}';

    protected $description = 'Подтянуть серию/номер паспорта из регистра 1С (inforeg) для клиентов с user_uid';

    public function handle(LmbContragentSyncService $sync): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только при LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        $cfg = config('services.lmb_1c_contragent_sync', []);
        $table = $cfg['inforeg_passport_table'] ?? '';
        $this->info('Регистр паспортов 1С: public.'.preg_replace('/[^a-z0-9_]/i', '', (string) $table));

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('Режим dry-run: изменений в БД не будет.');
        }
        if ($force) {
            $this->warn('Режим --force: существующий passport_data будет заменён.');
        }

        $result = $sync->syncPassportsFromInforegForExistingClients($dryRun, $force);

        $this->table(
            ['Обновлено', 'Пропущено (уже есть паспорт)', 'Нет данных в регистре'],
            [[$result['updated'], $result['skipped_existing_passport'], $result['not_in_inforeg']]]
        );

        if (! empty($result['errors'])) {
            $this->warn('Ошибки:');
            foreach (array_slice($result['errors'], 0, 15) as $err) {
                $this->line('  '.$err);
            }
        }

        if ($this->option('with-identity') && ! $dryRun) {
            $this->newLine();
            $this->info('Дозаполнение реквизитов удостоверения личности (документ 517, vt3220)...');
            $idRes = $sync->syncIdentityDetailsFrom1cForExistingClients(false);
            $this->line('  Обновлено записей: '.$idRes['updated']);
            if (! empty($idRes['errors'])) {
                foreach (array_slice($idRes['errors'], 0, 10) as $err) {
                    $this->warn('  '.$err);
                }
            }
        }

        $this->info('Готово.');

        return self::SUCCESS;
    }
}
