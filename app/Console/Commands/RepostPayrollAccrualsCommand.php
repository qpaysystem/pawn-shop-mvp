<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\PayrollAccrual;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/** Перепровести все начисления ФОТ в бухгалтерию (Дт 44 Кт 70) для отображения в ОСВ. */
class RepostPayrollAccrualsCommand extends Command
{
    protected $signature = 'payroll:repost
                            {--dry-run : Не создавать проводки, только показать список}';

    protected $description = 'Перепровести все документы начисления ФОТ: создать проводки Дт 44 Кт 70 для отображения в ОСВ';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $accruals = PayrollAccrual::where('total_amount', '>', 0)->orderBy('id')->get();

        if ($accruals->isEmpty()) {
            $this->info('Нет документов начисления ФОТ с суммой > 0.');
            return self::SUCCESS;
        }

        $ledger = app(LedgerService::class);
        $reposted = 0;
        $skipped = 0;

        foreach ($accruals as $accrual) {
            $hasEntry = LedgerEntry::where('document_type', 'payroll_accrual')
                ->where('document_id', $accrual->id)
                ->exists();

            if ($hasEntry) {
                $this->line("  [пропуск] {$accrual->number} — проводки уже есть");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [dry-run] {$accrual->number} — будет проведён: " . number_format($accrual->total_amount, 2, ',', ' ') . ' ₽');
                $reposted++;
                continue;
            }

            $ledger->post(
                '44',
                Account::CODE_PAYROLL,
                (float) $accrual->total_amount,
                Carbon::parse($accrual->accrual_date),
                null,
                'payroll_accrual',
                $accrual->id,
                'Начисление ФОТ ' . $accrual->number
            );
            $this->line("  [проведён] {$accrual->number} — " . number_format($accrual->total_amount, 2, ',', ' ') . ' ₽');
            $reposted++;
        }

        $this->newLine();
        $this->info("Готово. Проведено: {$reposted}, пропущено (уже были): {$skipped}.");
        return self::SUCCESS;
    }
}
