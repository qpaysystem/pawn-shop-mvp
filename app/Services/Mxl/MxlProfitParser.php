<?php

namespace App\Services\Mxl;

use Carbon\Carbon;
use RuntimeException;

/** Парсер MOXCEL «Отчет по валовой прибыли». */
class MxlProfitParser
{
    private const BRANCH_PREFIXES = [
        'Дуси Ковальчук',
        'Горский',
        'Мичурина',
        'Колхидская',
        'Станиславского',
        'Титова',
        'Карла Маркса',
        'Волховская',
        'Комиссионка',
    ];

    /**
     * @return list<array{
     *   store: string,
     *   date: ?Carbon,
     *   item: string,
     *   category: string,
     *   subcategory: string,
     *   cost: float,
     *   revenue: float,
     *   profit: float
     * }>
     */
    public function parse(string $filePath): array
    {
        $path = realpath($filePath) ?: $filePath;
        if (! is_file($path)) {
            throw new RuntimeException("Файл не найден: {$filePath}");
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Файл пустой или не читается.');
        }

        if (! str_starts_with($raw, 'MOXCEL')) {
            throw new RuntimeException('Ожидается формат MOXCEL (.mxl из 1С).');
        }

        $text = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        $rows = [];
        $lastStore = '';

        if (! preg_match_all('/\},1,\s*\n\{2,4,\s*\n\{"D",(\d{14})\}/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return $rows;
        }

        foreach ($matches[1] as [$dateRaw, $offset]) {
            $before = mb_substr($text, max(0, (int) $offset - 2500), 2500);
            $after = mb_substr($text, (int) $offset, 2500);

            $store = $this->lastBranchInText($before);
            if ($store === '') {
                $store = $this->lastBranchInText($after);
            }
            if ($store !== '') {
                $lastStore = $store;
            } elseif ($lastStore !== '') {
                $store = $lastStore;
            }

            $item = $this->extractQuoted($after, 2) ?? '';
            $category = $this->extractQuoted($after, 3) ?? '';
            $subcategory = $this->extractQuoted($after, 4) ?? '';
            $cost = $this->extractRowAmount($after, 5);
            $revenue = $this->extractRowAmount($after, 6);
            $profit = $this->extractRowAmount($after, 7);

            if ($item === '' && $cost <= 0 && $revenue <= 0) {
                continue;
            }

            $rows[] = [
                'store' => $store,
                'date' => $this->parseMoxcelDate((string) $dateRaw),
                'item' => $item,
                'category' => $category,
                'subcategory' => $subcategory,
                'cost' => $cost,
                'revenue' => $revenue,
                'profit' => $profit > 0 ? $profit : max(0, $revenue - $cost),
            ];
        }

        return $rows;
    }

    private function extractRowAmount(string $chunk, int $rowIndex): float
    {
        if (preg_match('/\},'.$rowIndex.',\s*\n\{2,\d+,\s*\n\{"N",([0-9.]+)\}/u', $chunk, $match)) {
            return (float) $match[1];
        }

        return 0.0;
    }

    private function extractQuoted(string $chunk, int $col): ?string
    {
        $pattern = '/\},'.$col.',\s*\n\{(?:16|20),\d+,[\s\S]{0,400}?\{"#","([^"]*)"\}/u';
        if (preg_match($pattern, $chunk, $match)) {
            $val = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);

            return $val !== '' ? $val : null;
        }

        return null;
    }

    private function lastBranchInText(string $text): string
    {
        $found = '';
        if (preg_match_all('/\{"#","([^"]+)"\}/u', $text, $matches)) {
            foreach ($matches[1] as $raw) {
                $val = html_entity_decode(trim((string) $raw), ENT_QUOTES | ENT_HTML5);
                foreach (self::BRANCH_PREFIXES as $prefix) {
                    if (str_starts_with($val, $prefix)) {
                        $found = $val;
                    }
                }
            }
        }

        return $found;
    }

    private function parseMoxcelDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if (! preg_match('/^\d{14}$/', $raw)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('YmdHis', $raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
