<?php

namespace App\Console\Commands;

use App\Services\Mxl\MxlReceiptsParser;
use App\Services\Mxl\ReceiptsMxlImportService;
use Illuminate\Console\Command;

/** Импорт поступлений (скупка + залог) из отчёта 1С MOXCEL (.mxl). */
class ImportReceiptsMxlCommand extends Command
{
    protected $signature = 'lmb:import-receipts-mxl
                            {file : Путь к Поступления.mxl}
                            {--dry-run : Только разбор и подсчёт}
                            {--force : Без подтверждения}
                            {--preview=10 : Сколько строк показать в dry-run}';

    protected $description = 'Импорт поступлений из MOXCEL: торговые точки, клиенты, залоги и скупки с номерами 00БП-*';

    public function handle(ReceiptsMxlImportService $import): int
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
                $parser = new MxlReceiptsParser;
                $rows = $parser->parse($real);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $pawn = count(array_filter($rows, fn ($r) => $r['type'] === 'pawn'));
            $purchase = count(array_filter($rows, fn ($r) => $r['type'] === 'purchase'));
            $stores = array_values(array_unique(array_map(
                fn ($r) => $import->normalizeStoreName($r['store']),
                $rows
            )));

            $this->info('Разобрано строк: '.count($rows));
            $this->line("Залоги: {$pawn}, скупки: {$purchase}");
            $this->line('Торговые точки ('.count($stores).'): '.implode(', ', $stores));

            $preview = max(1, (int) $this->option('preview'));
            $this->table(
                ['Тип', 'Номер', 'Дата', 'Точка', 'Клиент', 'Вещь', 'Сумма'],
                array_map(fn ($r) => [
                    $r['type'] === 'purchase' ? 'скупка' : 'залог',
                    $r['contract_number'],
                    $r['date']?->format('d.m.Y H:i') ?? '—',
                    mb_substr($r['store'], 0, 24),
                    mb_substr($r['client'], 0, 24),
                    mb_substr($r['item'], 0, 32),
                    number_format($r['amount'], 0, '.', ' '),
                ], array_slice($rows, 0, $preview))
            );

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Создать точки, клиентов, залоги и скупки из MOXCEL?', false)) {
            return self::SUCCESS;
        }

        $result = $import->import($real, false);
        foreach ($result['errors'] as $err) {
            $this->error($err);
        }

        $s = $result['stats'];
        $this->info('Разобрано строк: '.$result['parsed']);
        $this->table(
            ['Показатель', 'Кол-во'],
            [
                ['Торговые точки созданы', $s['stores_created']],
                ['Клиенты найдены в БД', $s['clients_matched']],
                ['Клиенты созданы', $s['clients_created']],
                ['Товары', $s['items_created']],
                ['Договоры залога', $s['pawn_created']],
                ['Договоры скупки', $s['purchase_created']],
                ['Пропущено', $s['skipped']],
            ]
        );

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
