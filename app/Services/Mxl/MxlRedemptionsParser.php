<?php

namespace App\Services\Mxl;

use Carbon\Carbon;
use RuntimeException;

/** Парсер MOXCEL «Выкупы / реализация» (выкуп залога + продажи). */
class MxlRedemptionsParser
{
    /**
     * @return list<array{
     *   date: ?Carbon,
     *   document: string,
     *   contract_number: string,
     *   type: 'redeem'|'sale'|'return',
     *   store: string,
     *   client: string,
     *   item: string,
     *   amount: float
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
        $chunks = preg_split('/\},\d+,0,6,0,\s*\n/u', $text) ?: [];
        $rows = [];

        foreach (array_slice($chunks, 1) as $chunk) {
            $row = $this->parseChunk($chunk);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function parseChunk(string $chunk): ?array
    {
        if (! preg_match('/\{2,3,\s*\n\{"D",(\d+)\}/u', $chunk, $dateMatch)) {
            return null;
        }

        if (! preg_match('/\{"#","((?:Операция по залогу|Реализация|Возврат)[^"]+)"\}/u', $chunk, $docMatch)) {
            return null;
        }

        if (! preg_match('/\},2,\s*\n\{16,4,.*?\n\{"#","([^"]+)"\}/us', $chunk, $storeMatch)) {
            return null;
        }

        if (! preg_match('/\},3,\s*\n\{16,4,.*?\n\{"#","([^"]+)"\}/us', $chunk, $clientMatch)) {
            return null;
        }

        $item = null;
        if (preg_match('/\},4,\s*\n\{20,4,.*?\n\},\s*\n\{1,1,\s*\n\{"#","([^"]+)"\}/us', $chunk, $itemMatch)) {
            $item = $itemMatch[1];
        } elseif (preg_match('/\},4,\s*\n\{16,4,.*?\n\{"#","([^"]+)"\}/us', $chunk, $itemMatch)) {
            $item = $itemMatch[1];
        }

        if ($item === null || ! preg_match('/\{2,5,\s*\n\{"N",([0-9.]+)\}/u', $chunk, $amountMatch)) {
            return null;
        }

        $document = html_entity_decode(trim($docMatch[1]), ENT_QUOTES | ENT_HTML5);
        $contractNumber = '';
        if (preg_match('/(00БП-\d{6})/u', $document, $numMatch)) {
            $contractNumber = $numMatch[1];
        }

        if ($contractNumber === '') {
            return null;
        }

        $type = 'redeem';
        if (str_starts_with($document, 'Реализация')) {
            $type = 'sale';
        } elseif (str_starts_with($document, 'Возврат')) {
            $type = 'return';
        }

        return [
            'date' => $this->parseMoxcelDate($dateMatch[1]),
            'document' => $document,
            'contract_number' => $contractNumber,
            'type' => $type,
            'store' => trim($storeMatch[1]),
            'client' => trim($clientMatch[1]),
            'item' => trim($item),
            'amount' => round((float) $amountMatch[1], 2),
        ];
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
