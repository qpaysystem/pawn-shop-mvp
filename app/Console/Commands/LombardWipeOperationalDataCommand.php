<?php

namespace App\Console\Commands;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\CallCenterContact;
use App\Models\CashDocument;
use App\Models\Client;
use App\Models\ClientVisit;
use App\Models\CommissionContract;
use App\Models\Expense;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\LmbRegisterBalance;
use App\Models\Marketing2GisStat;
use App\Models\PawnContract;
use App\Models\PayrollAccrual;
use App\Models\PayrollAccrualItem;
use App\Models\PurchaseContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Полная очистка операционных данных: клиенты, товары, договоры, касса/банк/расходы/ФОТ,
 * визиты, колл-центр, проводки ОСВ, синк-остатки LMB, ручная статистика 2ГИС.
 * Не трогает: магазины, пользователей, справочники (категории, счета, шаблоны проводок, счета в банке, сотрудников).
 */
class LombardWipeOperationalDataCommand extends Command
{
    protected $signature = 'lombard:wipe-operational
                            {--force : Выполнить без подтверждения}';

    protected $description = 'Удалить всех клиентов, товары, договоры, документы (касса, банк, расходы, ФОТ), визиты, колл-центр и все проводки ОСВ';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('Удалить ВСЕХ клиентов, товары, договоры, кассу, выписки, расходы, ФОТ, визиты, колл-центр и проводки ОСВ? Справочники и пользователи останутся. Действие необратимо.')) {
                $this->info('Отменено.');

                return self::SUCCESS;
            }
        }

        DB::beginTransaction();
        try {
            $this->info('Проводки (ОСВ)…');
            $n = LedgerEntry::query()->delete();
            $this->line("  Удалено: {$n}");

            $this->info('Строки и документы банковских выписок…');
            $n = BankStatementLine::query()->delete();
            $this->line("  Строк выписок: {$n}");
            $n = BankStatement::query()->delete();
            $this->line("  Выписок: {$n}");

            $this->info('Кассовые документы…');
            $n = CashDocument::query()->delete();
            $this->line("  Удалено: {$n}");

            $this->info('Расходы…');
            $n = Expense::query()->delete();
            $this->line("  Удалено: {$n}");

            $this->info('ФОТ (позиции и документы)…');
            $n = PayrollAccrualItem::query()->delete();
            $this->line("  Позиций: {$n}");
            $n = PayrollAccrual::query()->delete();
            $this->line("  Документов ФОТ: {$n}");

            $this->info('Визиты клиентов…');
            $n = ClientVisit::query()->delete();
            $this->line("  Удалено: {$n}");

            $this->info('Колл-центр…');
            $n = CallCenterContact::query()->delete();
            $this->line("  Удалено: {$n}");

            if (Schema::hasTable('lmb_register_balances')) {
                $this->info('Остатки синхронизации LMB…');
                $n = LmbRegisterBalance::query()->delete();
                $this->line("  Удалено: {$n}");
            }

            if (Schema::hasTable('marketing_2gis_stats')) {
                $this->info('Статистика 2ГИС (ручной ввод)…');
                $n = Marketing2GisStat::query()->delete();
                $this->line("  Удалено: {$n}");
            }

            $this->info('Договоры залога, скупки, комиссии…');
            $n = PawnContract::query()->delete();
            $this->line("  Залог: {$n}");
            $n = PurchaseContract::query()->delete();
            $this->line("  Скупка: {$n}");
            $n = CommissionContract::query()->delete();
            $this->line("  Комиссия: {$n}");

            $this->info('Товары (история статусов удалится каскадом при поддержке БД или отдельно)…');
            if (Schema::hasTable('item_status_history')) {
                DB::table('item_status_history')->delete();
            }
            $n = Item::query()->delete();
            $this->line("  Удалено товаров: {$n}");

            $this->info('Клиенты…');
            $n = Client::query()->delete();
            $this->line("  Удалено: {$n}");

            DB::commit();
            $this->newLine();
            $this->info('Готово. Операционная база очищена (клиенты, товары, документы, события, ОСВ).');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Ошибка: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
