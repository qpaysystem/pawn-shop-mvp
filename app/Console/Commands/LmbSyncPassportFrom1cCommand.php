<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\LmbContragentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LmbSyncPassportFrom1cCommand extends Command
{
    protected $signature = 'lmb:sync-passport-from-1c
                            {--stats : Только показать статистику в 1С и у нас, без загрузки}
                            {--dry-run : Не записывать в БД, только показать, сколько бы обновилось}';

    protected $description = 'Подтянуть паспортные данные из БД 1С для существующих клиентов (только по фамилии)';

    public function handle(LmbContragentSyncService $sync): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Работа с БД 1С возможна только при LMB_DB_DRIVER=pgsql. Проверьте .env.');

            return self::FAILURE;
        }

        $cfg = config('services.lmb_1c_contragent_sync', []);
        $table = preg_replace('/[^a-z0-9_]/i', '', $cfg['table'] ?? '_reference122x1');
        $passSeriesCol = ! empty($cfg['passport_series_column']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['passport_series_column']) : '_fld3202';
        $passNumberCol = ! empty($cfg['passport_number_column']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['passport_number_column']) : '_fld3201';

        if ($this->option('stats')) {
            return $this->showStats($table, $passSeriesCol, $passNumberCol);
        }

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->info('[dry-run] Запись в БД не выполняется.');
        }
        $this->info('Загрузка паспортных данных из 1С (только для существующих клиентов)...');
        $result = $sync->syncPassportOnly(function (int $processed, int $total) {
            $this->output->write("\r  Обработано клиентов: {$processed} / {$total}");
        }, $dryRun);
        $this->newLine();

        $this->table(
            ['В 1С с паспортом', 'Обновлено у нас', 'Нет совпадения', 'Уникальных фамилий в 1С (с однозначным паспортом)'],
            [[$result['from_1c_with_passport'], $result['updated'], $result['not_found'], $result['fio_keys_count'] ?? 0]]
        );

        if (! empty($result['errors'])) {
            $this->warn('Ошибки:');
            foreach (array_slice($result['errors'], 0, 10) as $err) {
                $this->line('  '.$err);
            }
            if (count($result['errors']) > 10) {
                $this->line('  ... и ещё '.(count($result['errors']) - 10));
            }
        }

        $this->info('Готово. Паспортные данные отображаются в карточке клиента.');

        return self::SUCCESS;
    }

    private function showStats(string $table, string $passSeriesCol, string $passNumberCol): int
    {
        try {
            $total1c = DB::connection('lmb_1c_pgsql')
                ->table("public.{$table}")
                ->whereRaw('NOT _marked')
                ->count();

            $withPassport1c = DB::connection('lmb_1c_pgsql')
                ->table("public.{$table}")
                ->whereRaw('NOT _marked')
                ->whereRaw("(TRIM(COALESCE(\"{$passSeriesCol}\"::text, '')) != '' OR TRIM(COALESCE(\"{$passNumberCol}\"::text, '')) != '')")
                ->count();

            $ourTotal = Client::where('client_type', Client::TYPE_INDIVIDUAL)->count();
            $ourWithPhoneOrUid = Client::where('client_type', Client::TYPE_INDIVIDUAL)
                ->where(function ($q) {
                    $q->whereNotNull('user_uid')->where('user_uid', '!=', '')
                        ->orWhereNotNull('phone')->where('phone', '!=', '');
                })
                ->count();
            $ourWithPassport = Client::whereNotNull('passport_data')->where('passport_data', '!=', '')->count();
        } catch (\Throwable $e) {
            $this->error('Ошибка запроса: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Статистика');
        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Контрагентов в 1С (физлица, без пометки)', $total1c],
                ['В 1С с заполненным паспортом (серия/номер)', $withPassport1c],
                ['Наших клиентов (физлица)', $ourTotal],
                ['Наших с телефоном или user_uid', $ourWithPhoneOrUid],
                ['У нас уже с паспортными данными', $ourWithPassport],
            ]
        );
        $this->line('Запустите без --stats для загрузки паспортов из 1С к нашим клиентам.');

        return self::SUCCESS;
    }
}
