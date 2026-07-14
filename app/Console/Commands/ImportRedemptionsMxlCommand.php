<?php

namespace App\Console\Commands;

use App\Services\Mxl\MxlRedemptionsParser;
use App\Services\Mxl\RedemptionsMxlImportService;
use Illuminate\Console\Command;

/** Импорт выкупов залога и реализаций из MOXCEL (.mxl). */
class ImportRedemptionsMxlCommand extends Command
{
    protected $signature = 'lmb:import-redemptions-mxl
                            {file : Путь к Выкупы.mxl}
                            {--dry-run : Сопоставление без записи в БД}
                            {--force : Без подтверждения}
                            {--preview=10 : Сколько строк показать}';

    protected $description = 'Импорт выкупов залога и реализаций из MOXCEL с сопоставлением остатков';

    public function handle(RedemptionsMxlImportService $import): int
    {
        $path = (string) $this->argument('file');
        $real = realpath($path) ?: $path;
        if (! is_file($real)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Режим dry-run — в БД ничего не пишется.');
            try {
                $parser = new MxlRedemptionsParser;
                $rows = $parser->parse($real);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $redeem = count(array_filter($rows, fn ($r) => $r['type'] === 'redeem'));
            $sale = count(array_filter($rows, fn ($r) => $r['type'] === 'sale'));
            $this->info('Разобрано строк: '.count($rows)." (выкупы: {$redeem}, реализации: {$sale})");

            $result = $import->import($real, true);
            $s = $result['stats'];
            $this->table(
                ['Показатель', 'Кол-во'],
                [
                    ['Выкупы — найдены в остатках', $s['redeemed']],
                    ['Выкупы — не найдены', $s['redeem_not_found']],
                    ['Реализации — товар найден', $s['sales_created']],
                    ['Реализации — товар не найден', $s['sale_not_found']],
                ]
            );

            $preview = max(1, (int) $this->option('preview'));
            $this->table(
                ['Тип', 'Номер', 'Дата', 'Точка', 'Клиент', 'Вещь', 'Сумма'],
                array_map(fn ($r) => [
                    match ($r['type']) {
                        'sale' => 'реализация',
                        'return' => 'возврат',
                        default => 'выкуп',
                    },
                    $r['contract_number'],
                    $r['date']?->format('d.m.Y H:i') ?? '—',
                    mb_substr($r['store'], 0, 20),
                    mb_substr($r['client'], 0, 20),
                    mb_substr($r['item'], 0, 28),
                    number_format($r['amount'], 0, '.', ' '),
                ], array_slice($rows, 0, $preview))
            );

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Провести выкупы и реализации из MOXCEL?', false)) {
            return self::SUCCESS;
        }

        $result = $import->import($real, false);
        foreach ($result['errors'] as $err) {
            $this->error($err);
        }
        foreach ($result['warnings'] as $warn) {
            $this->warn($warn);
        }

        $s = $result['stats'];
        $this->info('Разобрано строк: '.$result['parsed']);
        $this->table(
            ['Показатель', 'Кол-во'],
            [
                ['Выкупы проведены', $s['redeemed']],
                ['Выкупы — уже были', $s['redeem_already']],
                ['Выкупы — не найдены', $s['redeem_not_found']],
                ['Реализации созданы', $s['sales_created']],
                ['Реализации — уже были', $s['sale_already']],
                ['Реализации — товар не найден', $s['sale_not_found']],
                ['Возвраты пропущены', $s['returns_skipped']],
                ['Прочее пропущено', $s['skipped']],
            ]
        );

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
