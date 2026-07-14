<?php

namespace App\Console\Commands;

use App\Services\Mxl\InventoryMxlReconcileService;
use App\Services\Mxl\MxlInventoryParser;
use App\Services\Mxl\RedemptionsMxlImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/** Сверка инвентарки MOXCEL с остатками на дату + выкупы/реализации. */
class ReconcileInventoryMxlCommand extends Command
{
    protected $signature = 'lmb:reconcile-inventory-mxl
                            {inventory : Путь к инвентарке .mxl}
                            {--as-of=2026-04-01 : Дата среза остатков (Y-m-d)}
                            {--redemptions= : Путь к Выкупы,реализация.mxl — провести после сверки}
                            {--purchase-only : Все недостающие позиции создать как скупку}
                            {--dry-run : Только сравнение}
                            {--force : Без подтверждения}';

    protected $description = 'Сверить инвентарку 1С с остатками портала, дозаполнить скупками/залогами, провести выкупы и реализации';

    public function handle(
        InventoryMxlReconcileService $reconcile,
        MxlInventoryParser $parser,
        RedemptionsMxlImportService $redemptions,
    ): int {
        $invPath = (string) $this->argument('inventory');
        $realInv = realpath($invPath) ?: $invPath;
        if (! is_file($realInv)) {
            $this->error('Файл инвентарки не найден: '.$invPath);

            return self::FAILURE;
        }

        $asOf = Carbon::parse((string) $this->option('as-of'))->endOfDay();
        $dryRun = (bool) $this->option('dry-run');
        $purchaseOnly = (bool) $this->option('purchase-only');

        try {
            $parsed = $parser->parse($realInv);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $pawn = count(array_filter($parsed, fn (array $r) => $r['type'] === 'pawn'));
        $purchase = count(array_filter($parsed, fn (array $r) => $r['type'] === 'purchase'));
        $this->info("Инвентарка: {$pawn} залогов (ЕЛЗ), {$purchase} витрина/скупка (00БП), всего ".count($parsed));
        $this->line('Срез остатков портала на: '.$asOf->format('d.m.Y'));

        if ($dryRun) {
            $this->warn('Режим dry-run.');
        } elseif (! $this->option('force') && ! $this->confirm('Выполнить сверку и дозаполнение?', false)) {
            return self::SUCCESS;
        }

        $result = $reconcile->reconcile($realInv, $asOf, $purchaseOnly, $dryRun);
        $s = $result['stats'];
        $this->table(['Показатель', 'Кол-во'], [
            ['Строк в инвентарке', $s['inventory_rows']],
            ['Совпало с порталом', $s['matched']],
            ['Не хватает в портале', $s['missing']],
            ['Создано скупок', $s['purchases_created']],
            ['Создано залогов', $s['pawns_created']],
            ['Пропущено', $s['skipped']],
        ]);

        if (! empty($result['missing_samples'])) {
            $this->line('Примеры недостающих:');
            $this->table(
                ['Документ', 'Тип', 'Точка', 'Вещь', 'Сумма'],
                array_map(fn (array $r) => [
                    $r['document'],
                    $r['type'] === 'purchase' ? 'скупка' : 'залог',
                    mb_substr((string) $r['store'], 0, 22),
                    mb_substr((string) $r['item'], 0, 36),
                    number_format((float) $r['amount'], 0, '.', ' '),
                ], $result['missing_samples'])
            );
        }

        $redPath = trim((string) $this->option('redemptions'));
        if ($redPath === '' || $dryRun) {
            return self::SUCCESS;
        }

        $realRed = realpath($redPath) ?: $redPath;
        if (! is_file($realRed)) {
            $this->error('Файл выкупов не найден: '.$redPath);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Провести выкупы и реализации из MOXCEL?', true)) {
            return self::SUCCESS;
        }

        $this->info('Импорт выкупов и реализаций...');
        $redResult = $redemptions->import($realRed, false);
        foreach ($redResult['warnings'] ?? [] as $w) {
            $this->warn($w);
        }
        foreach ($redResult['errors'] ?? [] as $e) {
            $this->error($e);
        }

        $rs = $redResult['stats'];
        $this->table(['Показатель', 'Кол-во'], [
            ['Выкупы проведены', $rs['redeemed']],
            ['Выкупы не найдены', $rs['redeem_not_found']],
            ['Реализации созданы', $rs['sales_created']],
            ['Реализации не найдены', $rs['sale_not_found']],
        ]);

        return ($redResult['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
