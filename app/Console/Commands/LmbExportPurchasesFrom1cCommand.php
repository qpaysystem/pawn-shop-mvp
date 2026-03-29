<?php

namespace App\Console\Commands;

use App\Services\LmbPurchaseExportFrom1cService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Выгрузка из 1С документов «Скупка ценностей» с даты: контрагент, номенклатура, сумма/номер/время, филиал, ссылки на вложения.
 */
class LmbExportPurchasesFrom1cCommand extends Command
{
    protected $signature = 'lmb:export-purchases-from-1c
                            {--from=2026-01-01 : Начало периода (дата документа 1С)}
                            {--to= : Конец периода включительно (необязательно)}
                            {--format=table : table | csv | json}
                            {--output= : Файл для csv/json (по умолчанию stdout для json, для csv — обязателен при большом объёме)}';

    protected $description = 'Экспорт скупки из БД 1С: покупатель, товар, операция, филиал, вложения (метаданные; бинарник фото в PostgreSQL обычно отдельно)';

    public function handle(LmbPurchaseExportFrom1cService $export): int
    {
        try {
            $from = Carbon::parse($this->option('from'))->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Некорректная дата --from: '.$this->option('from'));

            return self::FAILURE;
        }

        $to = null;
        if ($this->option('to')) {
            try {
                $to = Carbon::parse($this->option('to'))->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Некорректная дата --to: '.$this->option('to'));

                return self::FAILURE;
            }
        }

        try {
            $rows = $export->fetchRows($from, $to);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $n = count($rows);
        $this->info("Записей: {$n}");
        if ($n === 0) {
            return self::SUCCESS;
        }

        $format = strtolower((string) $this->option('format'));
        $output = $this->option('output');

        if ($format === 'json') {
            $payload = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($output) {
                file_put_contents($output, $payload);
                $this->info('Записано: '.$output);
            } else {
                $this->line($payload);
            }

            return self::SUCCESS;
        }

        if ($format === 'csv') {
            if (! $output) {
                $this->error('Для --format=csv укажите --output=/path/to/file.csv');

                return self::FAILURE;
            }
            $fp = fopen($output, 'w');
            if ($fp === false) {
                $this->error('Не удалось открыть файл: '.$output);

                return self::FAILURE;
            }
            $headers = array_keys($rows[0]);
            fputcsv($fp, $headers, ';');
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $v = $row[$h] ?? null;
                    if (is_bool($v)) {
                        $v = $v ? '1' : '0';
                    } elseif ($v !== null && ! is_scalar($v)) {
                        $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                    }
                    $line[] = $v;
                }
                fputcsv($fp, $line, ';');
            }
            fclose($fp);
            $this->info('Записано: '.$output);

            return self::SUCCESS;
        }

        $headers = array_keys($rows[0]);
        $this->table($headers, array_map(function (array $row) use ($headers) {
            $out = [];
            foreach ($headers as $h) {
                $v = $row[$h] ?? '';
                if (is_bool($v)) {
                    $v = $v ? 'true' : 'false';
                }
                $s = (string) $v;
                if (mb_strlen($s) > 64) {
                    $s = mb_substr($s, 0, 61).'…';
                }
                $out[] = $s;
            }

            return $out;
        }, $rows));

        $this->newLine();
        $this->comment('Подсказка: ответственный — колонка responsible_ref_hex (разрешение в 1С через метаданные или find_idrref_in_1c.php).');
        $this->comment('Вложение _fld46109rref в этой конфигурации часто указывает на папку витрины в справочнике файлов, не JPEG в таблице.');

        return self::SUCCESS;
    }
}
