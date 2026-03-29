<?php

namespace App\Console\Commands;

use App\Services\LmbPawnSyncService;
use Illuminate\Console\Command;

class LmbSyncPawnContractsCommand extends Command
{
    protected $signature = 'lmb:sync-pawn-contracts
                            {--dry-run : Не записывать в БД, только проверить подключение и конфиг}
                            {--all : Синхронизировать все залоги (не только действующие)}
                            {--with-register-balance : Только договоры с положительным остатком в регистре накопления 1С}
                            {--without-register-balance-filter : Не применять фильтр по остаткам (даже если включён в .env)}
                            {--force : Запуск без подтверждения}';

    protected $description = 'Синхронизировать действующие залоги из БД 1С (документ + номенклатура, условия займа) в pawn_contracts';

    public function handle(LmbPawnSyncService $sync): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Синхронизация залогов из 1С возможна только при LMB_DB_DRIVER=pgsql. Проверьте .env.');

            return self::FAILURE;
        }

        $table = config('services.lmb_1c_pawn_sync.document_table', '');
        if ($table === '') {
            $this->error('Не задана таблица документа залога. Укажите LMB_1C_PAWN_DOCUMENT_TABLE в .env.');
            $this->line('См. docs/LMB_1C_TABLES_STRUCTURE_AND_SYNC.md и docs/LMB_SYNC_PAWN.md');

            return self::FAILURE;
        }

        $this->info("Таблица документа залога: public.{$table}");
        $onlyActing = ! $this->option('all');
        $filterByBalanceRegister = $this->resolveFilterByBalanceRegister();

        if ($this->option('dry-run')) {
            $this->info('Режим dry-run: проверка конфига и подключения.');
            try {
                \Illuminate\Support\Facades\DB::connection('lmb_1c_pgsql')->getPdo();
                $count = \Illuminate\Support\Facades\DB::connection('lmb_1c_pgsql')
                    ->table($table)
                    ->whereRaw('NOT _marked')
                    ->count();
                $this->info("Записей в документе (без пометки): {$count}");
                if ($onlyActing && config('services.lmb_1c_pawn_sync.expiry_column')) {
                    $this->info('Будут учитываться только залоги с датой окончания >= сегодня.');
                }
                if ($filterByBalanceRegister) {
                    $this->info('Фильтр по регистру остатков: да (только договоры с положительным остатком).');
                }
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->getOutput()->isInteractive() && ! $this->confirm('Запустить синхронизацию залогов из 1С?', true)) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $modeLine = $onlyActing ? 'Синхронизация только действующих залогов' : 'Синхронизация всех залогов';
        if ($filterByBalanceRegister) {
            $modeLine .= ' (только с остатком в регистре 1С)';
        }
        $this->info($modeLine.'...');
        $result = $sync->sync($onlyActing, function (int $processed, int $total) {
            $this->output->write("\r  Обработано: {$processed} / {$total}");
        }, $filterByBalanceRegister);
        $this->newLine();

        $this->table(
            ['Создано', 'Обновлено', 'Пропущено', 'Нет клиента', 'Сумма 0', 'Нет даты займа'],
            [[
                $result['created'],
                $result['updated'],
                $result['skipped'],
                $result['skipped_no_client'] ?? 0,
                $result['skipped_zero_amount'] ?? 0,
                $result['skipped_no_loan_date'] ?? 0,
            ]]
        );

        if (! empty($result['balance_register_meta'])) {
            $m = $result['balance_register_meta'];
            $this->line(sprintf(
                '  Регистр остатков: до фильтра документов %d, с остатком > 0 (по join) %d, после пересечения с выборкой %d.',
                $m['docs_before_filter'] ?? 0,
                $m['docs_with_balance'] ?? 0,
                $m['docs_after_filter'] ?? 0
            ));
            if (($m['docs_after_filter'] ?? 0) === 0 && ($m['docs_with_balance'] ?? 0) > 0) {
                $this->warn('  После фильтра не осталось документов: проверьте соответствие номера в документе 1С и кода справочника 252 (как в инвентарке: ЕЛЗ-NNNNN-ДК → цифры и lpad к номеру _number).');
            }
        }

        $noClient = $result['skipped_no_client'] ?? 0;
        if ($noClient > 0) {
            $this->warn("Из них контрагент не найден в нашей базе: {$noClient}.");
            $this->line('  Сначала выполните: <comment>php artisan lmb:sync-contragents</comment> (клиенты из 1С в раздел «Клиенты»).');
            $this->line('  Если клиенты уже синхронизированы — в документе залога контрагент может ссылаться на другой справочник (не физлица _reference122x1).');
        }

        $zeroAmt = (int) ($result['skipped_zero_amount'] ?? 0);
        if ($zeroAmt > 0 && (int) $result['created'] === 0 && (int) $result['updated'] === 0) {
            $this->warn("Пропуск из‑за суммы займа ≤ 0: {$zeroAmt}. Задайте в .env <comment>LMB_1C_PAWN_AMOUNT_COLUMN</comment> для таблицы залога (для _document41694x1 — <comment>_fld41697</comment>).");
        }

        if (! empty($result['errors'])) {
            $this->warn('Ошибки:');
            foreach (array_slice($result['errors'], 0, 15) as $err) {
                $this->line('  '.$err);
            }
            if (count($result['errors']) > 15) {
                $this->line('  ... и ещё '.(count($result['errors']) - 15));
            }
        }

        $this->info('Готово. Договоры залога из 1С отображаются в разделе «Документы» / «Договоры залога».');

        return self::SUCCESS;
    }

    private function resolveFilterByBalanceRegister(): bool
    {
        if ($this->option('without-register-balance-filter')) {
            return false;
        }
        if ($this->option('with-register-balance')) {
            return true;
        }

        return (bool) config('services.lmb_1c_pawn_sync.filter_by_balance_register', false);
    }
}
