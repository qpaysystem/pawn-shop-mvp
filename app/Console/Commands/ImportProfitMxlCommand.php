<?php

namespace App\Console\Commands;

use App\Services\Lombard\LombardReportService;
use App\Services\Mxl\MxlProfitParser;
use App\Services\Mxl\ProfitMxlImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/** Сравнение и догрузка валовой прибыли из MOXCEL 1С. */
class ImportProfitMxlCommand extends Command
{
    protected $signature = 'lmb:import-profit-mxl
                            {profit : Путь к прибыль.mxl}
                            {--from= : Начало периода (Y-m-d)}
                            {--to= : Конец периода (Y-m-d)}
                            {--compare-only : Только сравнить с порталом}
                            {--dry-run : План без записи}
                            {--force : Без подтверждения}';

    protected $description = 'Сравнить валовую прибыль 1С с порталом и догрузить выкупы/реализации';

    public function handle(
        MxlProfitParser $parser,
        ProfitMxlImportService $import,
        LombardReportService $reports,
    ): int {
        $path = realpath((string) $this->argument('profit')) ?: (string) $this->argument('profit');
        if (! is_file($path)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        try {
            $rows = $parser->parse($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $from = $this->option('from') ? Carbon::parse((string) $this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to'))->endOfDay() : null;

        if (! $from || ! $to) {
            $dates = array_filter(array_map(fn (array $row) => $row['date'] ?? null, $rows), fn ($d) => $d instanceof Carbon);
            if ($dates !== []) {
                usort($dates, fn (Carbon $a, Carbon $b) => $a <=> $b);
                $from = $dates[0]->copy()->startOfMonth();
                $to = $dates[array_key_last($dates)]->copy()->endOfMonth();
            }
        }

        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        $filtered = array_values(array_filter($rows, function (array $row) use ($from, $to) {
            $date = $row['date'] ?? null;
            if (! $date instanceof Carbon) {
                return true;
            }

            return $date->between($from, $to);
        }));

        $mxl = ['count' => 0, 'cost' => 0.0, 'revenue' => 0.0, 'profit' => 0.0];
        $byCat = [];
        foreach ($filtered as $row) {
            $cat = (string) ($row['category'] ?: 'Прочее');
            $mxl['count']++;
            $mxl['cost'] += (float) $row['cost'];
            $mxl['revenue'] += (float) $row['revenue'];
            $mxl['profit'] += (float) $row['profit'];
            if (! isset($byCat[$cat])) {
                $byCat[$cat] = ['category' => $cat, 'count' => 0, 'profit' => 0.0];
            }
            $byCat[$cat]['count']++;
            $byCat[$cat]['profit'] += (float) $row['profit'];
        }

        $portal = $reports->grossProfit(
            \App\Models\Store::pluck('id')->all(),
            null,
            $from,
            $to,
        );

        $this->info('Период: '.$from->format('d.m.Y').' — '.$to->format('d.m.Y'));
        $this->table(['Источник', 'Операций', 'Себестоимость', 'Реализация', 'Прибыль'], [
            ['1С (mxl)', $mxl['count'], $this->money($mxl['cost']), $this->money($mxl['revenue']), $this->money($mxl['profit'])],
            ['Портал', $portal['totals']['count'], $this->money((float) $portal['totals']['cost']), $this->money((float) $portal['totals']['revenue']), $this->money((float) $portal['totals']['profit'])],
            ['Разница', $mxl['count'] - $portal['totals']['count'], '—', '—', $this->money($mxl['profit'] - (float) $portal['totals']['profit'])],
        ]);

        $this->line('1С по категориям:');
        $this->table(['Категория', 'Операций', 'Прибыль'], array_map(
            fn (array $r) => [$r['category'], $r['count'], $this->money($r['profit'])],
            array_values($byCat),
        ));

        if ($this->option('compare-only')) {
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Режим dry-run.');
        } elseif (! $this->option('force') && ! $this->confirm('Догрузить недостающие выкупы и реализации?', false)) {
            return self::SUCCESS;
        }

        $result = $import->import($path, $from, $to, $dryRun);
        $s = $result['stats'];
        $this->table(['Показатель', 'Кол-во'], [
            ['Строк в файле', $s['parsed']],
            ['Выкупы применены', $s['redeems_applied']],
            ['Выкупы уже были', $s['redeems_already']],
            ['Выкупы не найдены', $s['redeems_not_found']],
            ['Реализации созданы', $s['sales_created']],
            ['Реализации уже были', $s['sales_already']],
            ['Реализации не найдены', $s['sales_not_found']],
        ]);

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        if (! $dryRun) {
            $after = $reports->grossProfit(\App\Models\Store::pluck('id')->all(), null, $from, $to);
            $this->info('Портал после догрузки: прибыль '.$this->money((float) $after['totals']['profit']));
        }

        return self::SUCCESS;
    }

    private function money(float $value): string
    {
        return number_format($value, 0, '.', ' ').' ₽';
    }
}
