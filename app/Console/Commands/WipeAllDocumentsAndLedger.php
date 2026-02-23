<?php

namespace App\Console\Commands;

use App\Models\CallCenterContact;
use App\Models\CashDocument;
use App\Models\CommissionContract;
use App\Models\ClientVisit;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\PawnContract;
use App\Models\PayrollAccrual;
use App\Models\PayrollAccrualItem;
use App\Models\PurchaseContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Полная очистка: все проводки (ОСВ) и все документы.
 * Договоры залога/комиссии/скупки, касса, ФОТ, расходы. Справочники и пользователи не трогаем.
 */
class WipeAllDocumentsAndLedger extends Command
{
    protected $signature = 'lombard:wipe-all-documents
                            {--force : Выполнить без подтверждения}';

    protected $description = 'Удалить все документы и все проводки (ОСВ) — база документов и бухгалтерии пустая';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('Удалить ВСЕ проводки (ОСВ) и ВСЕ документы: касса, договоры залога/комиссии/скупки, ФОТ, расходы? Это необратимо.')) {
                $this->info('Отменено.');
                return self::SUCCESS;
            }
        }

        DB::beginTransaction();
        try {
            $this->info('Удаление всех бухгалтерских проводок (ОСВ)…');
            $nLedger = LedgerEntry::query()->delete();
            $this->line("  Удалено проводок: {$nLedger}");

            $this->info('Удаление кассовых документов…');
            $nCash = CashDocument::query()->delete();
            $this->line("  Удалено кассовых документов: {$nCash}");

            $this->info('Обнуление привязок к договорам в визитах и колл-центре…');
            ClientVisit::query()->update([
                'pawn_contract_id' => null,
                'purchase_contract_id' => null,
                'commission_contract_id' => null,
            ]);
            CallCenterContact::query()->update([
                'pawn_contract_id' => null,
                'purchase_contract_id' => null,
                'commission_contract_id' => null,
            ]);
            $this->line('  Готово.');

            $this->info('Удаление договоров залога…');
            $nPawn = PawnContract::query()->delete();
            $this->line("  Удалено: {$nPawn}");

            $this->info('Удаление договоров скупки…');
            $nPurchase = PurchaseContract::query()->delete();
            $this->line("  Удалено: {$nPurchase}");

            $this->info('Удаление договоров комиссии…');
            $nComm = CommissionContract::query()->delete();
            $this->line("  Удалено: {$nComm}");

            $this->info('Удаление начислений ФОТ (позиции и документы)…');
            $nPayrollItems = PayrollAccrualItem::query()->delete();
            $nPayroll = PayrollAccrual::query()->delete();
            $this->line("  Удалено документов ФОТ: {$nPayroll}, позиций: {$nPayrollItems}");

            $this->info('Удаление расходов…');
            $nExpense = Expense::query()->delete();
            $this->line("  Удалено расходов: {$nExpense}");

            DB::commit();
            $this->newLine();
            $this->info('Готово. Все документы и проводки (ОСВ) удалены. База документов и бухгалтерии пустая.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
