<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Разбор файла инвентарки (XLS/XLSX) из 1С: вывод структуры листов, заголовков и примеров строк.
 * Помогает сопоставить колонки отчёта с таблицами БД 1С.
 */
class LmbParseInventoryXlsCommand extends Command
{
    protected $signature = 'lmb:parse-inventory-xls
                            {file : Путь к файлу Инвентарка.xls или .xlsx}
                            {--sheet= : Имя или индекс листа (0-based); по умолчанию первый}
                            {--rows=25 : Сколько строк данных вывести}
                            {--csv : Вывести первые строки в CSV (для сопоставления с БД)}';

    protected $description = 'Разобрать структуру файла инвентарки из 1С (колонки, примеры данных)';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_string($path) || $path === '') {
            $this->error('Укажите путь к файлу: php artisan lmb:parse-inventory-xls /path/to/Инвентарка.xls');

            return self::FAILURE;
        }

        $path = realpath($path) ?: $path;
        if (! is_file($path)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $this->error('Не удалось прочитать файл: '.$e->getMessage());

            return self::FAILURE;
        }

        $sheetNames = $spreadsheet->getSheetNames();
        $this->info('Листов в файле: '.count($sheetNames));
        foreach ($sheetNames as $i => $name) {
            $this->line('  ['.$i.'] '.$name);
        }

        $sheetOption = $this->option('sheet');
        $sheetIndex = 0;
        if ($sheetOption !== null && $sheetOption !== '') {
            if (is_numeric($sheetOption)) {
                $sheetIndex = (int) $sheetOption;
            } else {
                $idx = array_search($sheetOption, $sheetNames, true);
                if ($idx !== false) {
                    $sheetIndex = $idx;
                }
            }
        }

        $sheet = $spreadsheet->getSheet($sheetIndex);
        $sheetName = $sheetNames[$sheetIndex] ?? ('Sheet'.$sheetIndex);
        $this->newLine();
        $this->info('Лист: '.$sheetName.' (индекс '.$sheetIndex.')');

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $this->line('Строк с данными: '.$highestRow.', колонок до: '.$highestColumn);

        $maxRows = (int) $this->option('rows');
        $asCsv = $this->option('csv');

        // В отчёте 1С строка 1 — заголовок отчёта, строка 4 — заголовки колонок (Филиал, Документ, Наименование...)
        $headerRow = 1;
        $firstCell = (string) $sheet->getCell('A1')->getValue();
        if (str_contains($firstCell, 'инвентар') || str_contains($firstCell, 'Отчет')) {
            $headerRow = 4;
            $this->line('Принята строка 4 как заголовок колонок (строка 1: «'.substr($firstCell, 0, 50).'…»).');
        }

        $headers = [];
        $colCount = Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $colCount; $col++) {
            $coord = Coordinate::stringFromColumnIndex($col).$headerRow;
            $val = $sheet->getCell($coord)->getValue();
            $headers[] = $val !== null ? trim((string) $val) : '';
        }

        $this->newLine();
        $this->line('<fg=cyan>Заголовок (строка '.$headerRow.'):</>');
        $this->table(
            array_map(fn ($i) => 'Col'.$i, range(1, count($headers))),
            [$headers]
        );

        $dataRows = [];
        $dataStartRow = $headerRow + 1;
        for ($row = $dataStartRow; $row <= min($highestRow, $dataStartRow + $maxRows - 1); $row++) {
            $cells = [];
            for ($col = 1; $col <= $colCount; $col++) {
                $coord = Coordinate::stringFromColumnIndex($col).$row;
                $cell = $sheet->getCell($coord);
                $v = $cell->getValue();
                if ($v instanceof \DateTimeInterface) {
                    $v = $v->format('Y-m-d H:i');
                }
                $cells[] = $v !== null ? (string) $v : '';
            }
            $dataRows[] = $cells;
        }

        $this->line('<fg=cyan>Данные (примеры строк):</>');
        if (empty($dataRows)) {
            $this->warn('Нет строк данных.');
        } else {
            $this->table(
                array_map(fn ($i) => 'Col'.$i, range(1, count($headers))),
                $dataRows
            );
        }

        if ($asCsv && ! empty($headers) && ! empty($dataRows)) {
            $this->newLine();
            $this->line('CSV (заголовок + первые строки):');
            $out = fopen('php://temp', 'r+');
            fputcsv($out, $headers, ';');
            foreach ($dataRows as $r) {
                fputcsv($out, $r, ';');
            }
            rewind($out);
            $this->line(stream_get_contents($out));
        }

        $this->newLine();
        $this->info('По этим колонкам можно искать соответствия в БД 1С: документы залога/скупки, номенклатура, остатки (регистры _accumrg*).');

        return self::SUCCESS;
    }
}
