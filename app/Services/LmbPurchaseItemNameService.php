<?php

namespace App\Services;

use App\Models\PurchaseContract;
use Illuminate\Support\Facades\DB;

/**
 * Обновить наименования товаров у договоров скупки из документа 1С.
 * Читает из заголовка документа (_document389x1) колонки _fld9638, _fld9643, _fld9650 (или из конфига)
 * и записывает первую непустую в item.name.
 */
class LmbPurchaseItemNameService
{
    private string $connection = 'lmb_1c_pgsql';

    private string $docTable;

    /** @var array<int, string> */
    private array $nameColumns;

    public function __construct()
    {
        $cfg = config('services.lmb_1c_purchase_item_name', []);
        $this->docTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['document_table'] ?? '_document389x1'));
        $raw = $cfg['name_columns'] ?? ['_fld9638', '_fld9643', '_fld9650'];
        $this->nameColumns = array_values(array_filter(array_map(function ($c) {
            return preg_replace('/[^a-z0-9_]/i', '', (string) $c);
        }, $raw)));
    }

    /**
     * Обновить наименования товаров по документам 1С.
     * Возвращает ['updated' => int, 'skipped' => int, 'errors' => string[]].
     */
    public function refresh(): array
    {
        $updated = 0;
        $skipped = 0;
        $errors = [];

        if ($this->docTable === '' || empty($this->nameColumns)) {
            $errors[] = 'Задайте document_table и name_columns в config/services.php (lmb_1c_purchase_item_name).';

            return ['updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
        }

        $selectCols = 'encode(_idrref, \'hex\') as doc_uid';
        foreach ($this->nameColumns as $col) {
            $selectCols .= ', "'.$col.'" as col_'.preg_replace('/[^a-z0-9_]/i', '_', $col);
        }

        $contracts = PurchaseContract::whereNotNull('lmb_doc_uid')
            ->with('item')
            ->get();

        foreach ($contracts as $contract) {
            $docUid = $contract->lmb_doc_uid;
            if (! $docUid || ! $contract->item_id) {
                $skipped++;

                continue;
            }

            try {
                $sql = "SELECT {$selectCols} FROM public.{$this->docTable} WHERE _idrref = decode(?, 'hex') AND NOT _marked LIMIT 1";
                $row = DB::connection($this->connection)->selectOne($sql, [$docUid]);
                if (! $row) {
                    $skipped++;

                    continue;
                }

                $nameFrom1c = null;
                foreach ($this->nameColumns as $col) {
                    $prop = 'col_'.preg_replace('/[^a-z0-9_]/i', '_', $col);
                    $val = $row->$prop ?? null;
                    if ($val !== null && trim((string) $val) !== '') {
                        $nameFrom1c = trim((string) $val);
                        if (mb_strlen($nameFrom1c) > 255) {
                            $nameFrom1c = mb_substr($nameFrom1c, 0, 255);
                        }
                        break;
                    }
                }

                if ($nameFrom1c === null) {
                    $skipped++;

                    continue;
                }

                $item = $contract->item;
                if (! $item) {
                    $skipped++;

                    continue;
                }

                $payload = [];

                if ($item->name !== $nameFrom1c) {
                    $payload['name'] = $nameFrom1c;
                }

                $parsed = self::parseItemString($nameFrom1c);
                if ($parsed['metal'] !== null && $item->metal !== $parsed['metal']) {
                    $payload['metal'] = $parsed['metal'];
                }
                if ($parsed['sample'] !== null && $item->sample !== $parsed['sample']) {
                    $payload['sample'] = $parsed['sample'];
                }
                if ($parsed['weight_grams'] !== null && (string) $item->weight_grams !== (string) $parsed['weight_grams']) {
                    $payload['weight_grams'] = $parsed['weight_grams'];
                }

                if (! empty($payload)) {
                    $item->update($payload);
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Договор {$contract->contract_number}: ".$e->getMessage();
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Примеры входа:
     * - "Цепь, Золото 585, 8,67 гр."
     * - "Зубной протез, Золото 850 (Зубные коронки), 15,2 гр."
     * - "Ноутбук ASUS ROG Strix G16 G614J"
     *
     * Возвращает: metal (string|null), sample (string|null), weight_grams (string|null).
     */
    public static function parseItemString(string $s): array
    {
        $metal = null;
        $sample = null;
        $weight = null;

        $normalized = trim(preg_replace('/\s+/u', ' ', $s));

        // Металл
        if (preg_match('/\b(Золото|Серебро|Платина|Палладий)\b/ui', $normalized, $m)) {
            $metal = mb_convert_case($m[1], MB_CASE_TITLE, 'UTF-8');
        }

        // Проба: чаще 3 цифры рядом с металлом, но бывает и отдельно
        if (preg_match('/\b(375|500|583|585|750|800|850|875|900|916|925|958|999)\b/u', $normalized, $m)) {
            $sample = $m[1];
        }

        // Вес в граммах: "8,67 гр" / "8.67 г" / "1 251 гр." (в т.ч. с неразрывным пробелом)
        if (preg_match('/\b(\d{1,3}(?:[ \x{00A0}]\d{3})*(?:[.,]\d{1,3})?)\s*(?:г|гр)\.?\b/ui', $normalized, $m)) {
            $num = str_replace(["\u{00A0}", ' '], '', $m[1]);
            $num = str_replace(',', '.', $num);
            if (is_numeric($num)) {
                // Храним как строку с точкой, чтобы корректно записалось в decimal(10,3)
                $weight = number_format((float) $num, 3, '.', '');
            }
        }

        return [
            'metal' => $metal,
            'sample' => $sample,
            'weight_grams' => $weight,
        ];
    }
}
