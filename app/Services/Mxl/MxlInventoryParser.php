<?php

namespace App\Services\Mxl;

use Carbon\Carbon;
use RuntimeException;

/** Парсер MOXCEL «Отчет по инвентарке» (залоги ЕЛЗ + витрина 00БП). */
class MxlInventoryParser
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
     *   document: string,
     *   type: 'pawn'|'purchase',
     *   store: string,
     *   status: string,
     *   category: string,
     *   item: string,
     *   client: string,
     *   phone: string,
     *   amount: float,
     *   loan_date: ?Carbon,
     *   expiry_date: ?Carbon,
     *   vitrina: string
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

        if (preg_match_all('/\{"#","((?:ЕЛЗ-|00БП-)[^"]+)"\}/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as [$docRaw, $offset]) {
                $row = $this->parseAtOffset($text, (int) $offset, (string) $docRaw);
                if ($row === null) {
                    continue;
                }

                $store = trim((string) ($row['store'] ?? ''));
                if ($store !== '') {
                    $lastStore = $store;
                } elseif ($lastStore !== '') {
                    $row['store'] = $lastStore;
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function parseAtOffset(string $text, int $offset, string $document): ?array
    {
        $document = html_entity_decode(trim($document), ENT_QUOTES | ENT_HTML5);
        if ($document === '') {
            return null;
        }

        $before = mb_substr($text, max(0, $offset - 8000), min(8000, $offset));
        $after = mb_substr($text, $offset, 4500);

        $store = $this->lastBranchInText($before);
        if ($store === '') {
            $store = $this->lastBranchInText($after);
        }
        $item = $this->extractAfter($after, 4, true) ?? '';
        if ($item === '' || mb_strlen($item) < 3) {
            $item = $this->extractQuotedAfterDoc($after) ?? '';
        }

        $client = $this->extractClient($after);
        $phone = preg_replace('/\D+/u', '', $this->extractAfter($after, 20, false) ?? '') ?? '';
        $status = $this->extractAfter($after, 2, false) ?? '';
        $category = $this->extractAfter($after, 3, false) ?? '';
        $vitrina = $this->extractAfter($after, 25, false) ?? '';

        $amount = $this->extractAmount($after);

        $dates = [];
        if (preg_match_all('/\{"D",(\d{14})\}/u', $after, $dateMatches)) {
            foreach ($dateMatches[1] as $d) {
                $dates[] = $this->parseMoxcelDate($d);
            }
        }

        $loanDate = $dates[0] ?? null;
        $expiryDate = $dates[1] ?? null;

        $isPurchase = str_starts_with($document, '00БП-');

        if (str_starts_with($document, 'ЕЛЗ-')) {
            $isPurchase = false;
        }

        if ($item === '' && $amount <= 0) {
            return null;
        }

        return [
            'document' => $document,
            'type' => $isPurchase ? 'purchase' : 'pawn',
            'store' => $store,
            'status' => $status,
            'category' => $category,
            'item' => $item,
            'client' => $client,
            'phone' => $phone,
            'amount' => $amount,
            'loan_date' => $loanDate,
            'expiry_date' => $expiryDate,
            'vitrina' => $vitrina,
        ];
    }

    private function extractClient(string $after): string
    {
        foreach ([18, 15, 21] as $col) {
            $val = $this->extractAfter($after, $col, true);
            if ($val !== null && $this->looksLikeClientName($val)) {
                return $val;
            }
        }

        return '';
    }

    private function looksLikeClientName(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) < 5) {
            return false;
        }

        $lower = mb_strtolower($value);
        if (str_contains($lower, 'оборудование ломбарда') || str_contains($lower, 'информационный')) {
            return false;
        }

        return (bool) preg_match('/[А-Яа-яЁё]/u', $value);
    }

    private function extractAmount(string $after): float
    {
        // Старый отчёт: сумма займа/скупки в кол. 9; с 2026 чаще в кол. 21.
        foreach ([9, 21] as $col) {
            if (preg_match('/\{2,'.$col.',\s*\n\{"N",([0-9.]+)\}/u', $after, $amountMatch)) {
                return (float) $amountMatch[1];
            }
        }

        return 0.0;
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

    private function extractAfter(string $chunk, int $col, bool $allowLong): ?string
    {
        $max = $allowLong ? 400 : 200;
        $pattern = '/\},'.$col.',\s*\n\{(?:16|20),\d+,[\s\S]{0,'.$max.'}?\{"#","([^"]*)"\}/u';
        if (preg_match($pattern, $chunk, $m)) {
            $val = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);

            return $val !== '' ? $val : null;
        }

        return null;
    }

    private function extractQuotedAfterDoc(string $after): ?string
    {
        if (preg_match('/\},4,\s*\n\{20,4,[\s\S]{0,500}?\{"#","([^"]{4,255})"\}/u', $after, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
        }

        return null;
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
