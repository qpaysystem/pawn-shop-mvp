<?php

namespace App\Console\Commands;

use App\Models\CallCenterContact;
use App\Models\CashDocument;
use App\Models\CashOperationType;
use App\Models\ClientVisit;
use App\Models\CommissionContract;
use App\Models\Item;
use App\Models\LedgerEntry;
use App\Models\PawnContract;
use App\Models\PurchaseContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Удаляет все товары и все документы по залогам и скупкам:
 * проводки, кассовые документы (выдача/возврат займа, оплата продавцу),
 * визиты и контакты колл-центра, договоры залога и скупки, договоры комиссии, товары.
 */
class WipePawnAndPurchaseData extends Command
{
    protected $signature = 'lombard:wipe-pawn-purchase
                            {--force : Выполнить без подтверждения}';

    protected $description = 'Удалить все товары, договоры залога и скупки и связанные документы';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('Удалить ВСЕ товары, договоры залога, скупки, комиссии и связанные кассовые документы и проводки? Это необратимо.')) {
                $this->info('Отменено.');
                return self::SUCCESS;
            }
        }

        DB::beginTransaction();
        try {
            $this->info('Удаление проводок по договорам залога, скупки и комиссии…');
            $le1 = LedgerEntry::whereIn('document_type', ['pawn_contract', 'purchase_contract', 'commission_contract'])->delete();
            $this->line("  Удалено проводок: {$le1}");

            $loanType = CashOperationType::findByName('Выдача займа');
            $purchasePayType = CashOperationType::findByName('Оплата продавцу');
            $repayType = CashOperationType::findByName('Возврат займа');
            $typeIds = array_filter([
                $loanType?->id,
                $purchasePayType?->id,
                $repayType?->id,
            ]);
            if ($typeIds !== []) {
                $cashDocIds = CashDocument::whereIn('operation_type_id', $typeIds)->pluck('id')->all();
                if ($cashDocIds !== []) {
                    $this->info('Удаление проводок по кассовым документам (займы/оплата/возврат)…');
                    $le2 = LedgerEntry::where('document_type', 'cash_document')->whereIn('document_id', $cashDocIds)->delete();
                    $this->line("  Удалено проводок: {$le2}");
                    $this->info('Удаление кассовых документов…');
                    $cd = CashDocument::whereIn('id', $cashDocIds)->delete();
                    $this->line("  Удалено кассовых документов: {$cd}");
                }
            }

            $this->info('Обнуление привязок к договорам в визитах и колл-центре…');
            ClientVisit::query()->update(['pawn_contract_id' => null, 'purchase_contract_id' => null, 'commission_contract_id' => null]);
            CallCenterContact::query()->update(['pawn_contract_id' => null, 'purchase_contract_id' => null, 'commission_contract_id' => null]);
            $this->line('  Готово.');

            $this->info('Удаление договоров залога…');
            $pc = PawnContract::query()->delete();
            $this->line("  Удалено: {$pc}");

            $this->info('Удаление договоров скупки…');
            $puc = PurchaseContract::query()->delete();
            $this->line("  Удалено: {$puc}");

            $this->info('Удаление договоров комиссии (связаны с товарами)…');
            $cc = CommissionContract::query()->delete();
            $this->line("  Удалено: {$cc}");

            $this->info('Удаление проводок по комиссии (если остались)…');
            $le3 = LedgerEntry::where('document_type', 'commission_contract')->delete();
            $this->line("  Удалено проводок: {$le3}");

            $this->info('Удаление товаров…');
            $items = Item::query()->delete();
            $this->line("  Удалено товаров: {$items}");

            DB::commit();
            $this->newLine();
            $this->info('Готово. Все товары и документы по залогам и скупкам удалены.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
