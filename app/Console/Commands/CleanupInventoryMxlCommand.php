<?php

namespace App\Console\Commands;

use App\Models\PawnContract;
use App\Models\PurchaseContract;
use App\Models\Store;
use App\Services\Mxl\InventoryMxlCleanupService;
use App\Services\Mxl\MxlInventoryParser;
use Carbon\Carbon;
use Illuminate\Console\Command;

/** Очистка остатков портала по инвентарке 1С. */
class CleanupInventoryMxlCommand extends Command
{
    protected $signature = 'lmb:cleanup-inventory-mxl
                            {inventory : Путь к инвентарке .mxl}
                            {--as-of=2026-07-06 : Дата среза 1С (Y-m-d)}
                            {--dry-run : Только план без удаления}
                            {--force : Без подтверждения}';

    protected $description = 'Удалить дубли и лишние остатки портала, привести к инвентарке 1С';

    public function handle(InventoryMxlCleanupService $cleanup, MxlInventoryParser $parser): int
    {
        $invPath = (string) $this->argument('inventory');
        $realInv = realpath($invPath) ?: $invPath;
        if (! is_file($realInv)) {
            $this->error('Файл инвентарки не найден: '.$invPath);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $asOf = Carbon::parse((string) $this->option('as-of'));

        try {
            $parsed = $parser->parse($realInv);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $pawn = count(array_filter($parsed, fn (array $r) => $r['type'] === 'pawn'));
        $purchase = count(array_filter($parsed, fn (array $r) => $r['type'] === 'purchase'));
        $this->info("Эталон 1С: {$pawn} залогов, {$purchase} скупок, всего ".count($parsed));
        $this->line('Срез: '.$asOf->format('d.m.Y'));

        if ($dryRun) {
            $this->warn('Режим dry-run — удаление не выполняется.');
        } elseif (! $this->option('force') && ! $this->confirm('Удалить лишние позиции и склады по плану?', false)) {
            return self::SUCCESS;
        }

        $storesBefore = Store::count();
        $result = $cleanup->cleanup($realInv, $asOf, $dryRun);
        $s = $result['stats'];

        $this->table(['Показатель', 'Значение'], [
            ['Строк в 1С', $s['inventory_rows']],
            ['Сумма 1С, ₽', number_format((float) $s['inventory_sum'], 0, '.', ' ')],
            ['Остатков в портале до', $s['portal_rows_before']],
            ['Оставить позиций', $s['keep_rows']],
            ['Сумма оставить, ₽', number_format((float) $s['keep_sum'], 0, '.', ' ')],
            ['Удалить позиций', $s['delete_rows']],
            ['Сумма удалить, ₽', number_format((float) $s['delete_sum'], 0, '.', ' ')],
            ['Удалить залогов', $s['delete_pawns']],
            ['Удалить скупок', $s['delete_purchases']],
            ['В 1С без пары в портале', $s['unclaimed_inventory_rows']],
        ]);

        if (! $dryRun) {
            $pawnCnt = PawnContract::where('is_redeemed', false)->count();
            $purchaseCnt = PurchaseContract::whereDoesntHave('item.saleContract')->count();
            $pawnSum = (float) PawnContract::where('is_redeemed', false)->sum('loan_amount');
            $purchaseSum = (float) PurchaseContract::whereDoesntHave('item.saleContract')->sum('purchase_amount');
            $this->newLine();
            $this->info('Портал после очистки:');
            $this->table(['Показатель', 'Значение'], [
                ['Позиций', $pawnCnt + $purchaseCnt],
                ['Сумма, ₽', number_format($pawnSum + $purchaseSum, 0, '.', ' ')],
                ['Залоги', $pawnCnt.' / '.number_format($pawnSum, 0, '.', ' ')],
                ['Скупки', $purchaseCnt.' / '.number_format($purchaseSum, 0, '.', ' ')],
                ['Складов', Store::count().' (было '.$storesBefore.')'],
            ]);
        }

        if (! empty($result['samples_delete'])) {
            $this->line('Примеры к удалению:');
            $this->table(
                ['Тип', 'Документ', 'Точка', 'Вещь', 'Сумма'],
                array_map(fn (array $r) => [
                    $r['kind'] === 'purchase' ? 'скупка' : 'залог',
                    $r['contract_number'],
                    mb_substr((string) $r['store'], 0, 22),
                    mb_substr((string) $r['item'], 0, 36),
                    number_format((float) $r['amount'], 0, '.', ' '),
                ], $result['samples_delete'])
            );
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
