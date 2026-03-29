<?php

namespace App\Services;

use App\Models\Marketing2GisStat;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

/**
 * Импорт статистики 2ГИС из выгрузок личного кабинета:
 * - pagevisits-daily.xlsx (переходы на страницу → views_count)
 * - connections-daily.xlsx (звонки и просмотры телефона → calls_count)
 */
class TwoGisStatsImportService
{
    /**
     * Загрузить данные из одного или двух xlsx, объединить по дате и сохранить.
     * Существующие значения по дате не затираются: подставляются только из загруженных файлов.
     *
     * @param  UploadedFile|null  $pagevisitsFile  pagevisits-daily.xlsx
     * @param  UploadedFile|null  $connectionsFile  connections-daily.xlsx
     * @return array{imported: int, errors: array<int, string>}
     */
    public function import(?UploadedFile $pagevisitsFile = null, ?UploadedFile $connectionsFile = null): array
    {
        $byDate = []; // date => ['views' => int, 'calls' => int]
        $errors = [];

        if ($pagevisitsFile) {
            $views = $this->parsePagevisits($pagevisitsFile->getPathname(), $errors);
            foreach ($views as $date => $count) {
                $byDate[$date] = ($byDate[$date] ?? []) + ['views' => $count, 'calls' => null];
            }
        }

        if ($connectionsFile) {
            $calls = $this->parseConnections($connectionsFile->getPathname(), $errors);
            foreach ($calls as $date => $count) {
                if (! isset($byDate[$date])) {
                    $byDate[$date] = ['views' => null, 'calls' => $count];
                } else {
                    $byDate[$date]['calls'] = $count;
                }
            }
        }

        if (empty($byDate)) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $existing = Marketing2GisStat::whereIn('date', array_keys($byDate))
            ->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $imported = 0;
        foreach ($byDate as $dateStr => $vals) {
            $existingRow = $existing->get($dateStr);
            $views = $vals['views'] ?? $existingRow?->views_count ?? 0;
            $calls = $vals['calls'] ?? $existingRow?->calls_count ?? 0;

            Marketing2GisStat::updateOrCreate(
                ['date' => $dateStr],
                [
                    'views_count' => (int) $views,
                    'calls_count' => (int) $calls,
                ]
            );
            $imported++;
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * Парсинг pagevisits-daily.xlsx: лист "Tables", с 6-й строки колонки B=дата, C=переходы на страницу.
     */
    private function parsePagevisits(string $path, array &$errors): array
    {
        $result = [];
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $row = 6;
            while (true) {
                $dateVal = $sheet->getCell('B'.$row)->getValue();
                $viewsVal = $sheet->getCell('C'.$row)->getValue();
                if ($dateVal === null && $viewsVal === null) {
                    break;
                }
                $dateStr = $this->normalizeDate($dateVal);
                if ($dateStr && is_numeric($viewsVal)) {
                    $result[$dateStr] = (int) $viewsVal;
                }
                $row++;
                if ($row > 10000) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'pagevisits: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Парсинг connections-daily.xlsx: лист "Tables", с 6-й строки колонки B=дата, C=звонки и просмотры телефона.
     */
    private function parseConnections(string $path, array &$errors): array
    {
        $result = [];
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $row = 6;
            while (true) {
                $dateVal = $sheet->getCell('B'.$row)->getValue();
                $callsVal = $sheet->getCell('C'.$row)->getValue();
                if ($dateVal === null && $callsVal === null) {
                    break;
                }
                $dateStr = $this->normalizeDate($dateVal);
                if ($dateStr && is_numeric($callsVal)) {
                    $result[$dateStr] = (int) $callsVal;
                }
                $row++;
                if ($row > 10000) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'connections: '.$e->getMessage();
        }

        return $result;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            try {
                $date = SpreadsheetDate::excelToDateTimeObject($value);

                return $date->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        $parsed = Carbon::createFromFormat('d.m.Y', trim((string) $value));
        if ($parsed) {
            return $parsed->format('Y-m-d');
        }

        return null;
    }
}
