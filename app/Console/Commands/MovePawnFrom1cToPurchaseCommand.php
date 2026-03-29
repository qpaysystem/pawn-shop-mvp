<?php

namespace App\Console\Commands;

use App\Models\PawnContract;
use App\Models\PurchaseContract;
use Illuminate\Console\Command;

/**
 * Перенести договоры, ошибочно загруженные из 1С как «залог», в раздел «Договоры скупки».
 * Выбираются только pawn_contracts с заполненным lmb_doc_uid (из синхронизации с _document389x1 — это скупка, не залог).
 */
class MovePawnFrom1cToPurchaseCommand extends Command
{
    protected $signature = 'lmb:move-pawn-from-1c-to-purchase
                            {--dry-run : Только показать, сколько договоров будет перенесено}
                            {--force : Выполнить без подтверждения}';

    protected $description = 'Перенести договоры из раздела залога в скупку (документы из 1С _document389x1 — это скупка)';

    public function handle(): int
    {
        $query = PawnContract::whereNotNull('lmb_doc_uid');
        $count = $query->count();

        if ($count === 0) {
            $this->info('Нет договоров залога из 1С (lmb_doc_uid пуст). Нечего переносить.');

            return self::SUCCESS;
        }

        $this->warn("Найдено договоров залога из 1С (будут перенесены в скупку): {$count}");

        if ($this->option('dry-run')) {
            $this->info('Запустите без --dry-run для переноса.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Перенести {$count} договоров в «Договоры скупки»? Договоры залога будут удалены.", true)) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $moved = 0;
        $errors = [];

        foreach ($query->get() as $pawn) {
            try {
                $contractNumber = $pawn->contract_number;
                if (PurchaseContract::where('contract_number', $contractNumber)->exists()) {
                    $contractNumber = PurchaseContract::generateContractNumber();
                }

                PurchaseContract::create([
                    'contract_number' => $contractNumber,
                    'lmb_doc_uid' => $pawn->lmb_doc_uid,
                    'lmb_data' => $pawn->lmb_data,
                    'client_id' => $pawn->client_id,
                    'item_id' => $pawn->item_id,
                    'appraiser_id' => $pawn->appraiser_id,
                    'store_id' => $pawn->store_id,
                    'purchase_amount' => $pawn->loan_amount,
                    'purchase_date' => $pawn->loan_date,
                ]);

                $pawn->delete();
                $moved++;
            } catch (\Throwable $e) {
                $errors[] = "Договор {$pawn->contract_number}: ".$e->getMessage();
            }
        }

        $this->info("Перенесено в скупку: {$moved}.");
        if (! empty($errors)) {
            $this->warn('Ошибки:');
            foreach (array_slice($errors, 0, 10) as $err) {
                $this->line('  '.$err);
            }
            if (count($errors) > 10) {
                $this->line('  ... и ещё '.(count($errors) - 10));
            }
        }

        return self::SUCCESS;
    }
}
