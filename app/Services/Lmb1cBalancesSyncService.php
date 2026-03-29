<?php

namespace App\Services;

use App\Models\LmbRegisterBalance;
use Illuminate\Support\Facades\DB;

/**
 * Синхронизация остатков из регистра накопления 1С (_accumrg*).
 * Суммирует движения по измерениям (quantity * recordkind, amount * recordkind) и сохраняет в lmb_register_balances.
 */
class Lmb1cBalancesSyncService
{
    private string $connection = 'lmb_1c_pgsql';

    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('services.lmb_1c_balances_sync', []);
    }

    /**
     * Синхронизировать остатки из настроенного регистра.
     * Возвращает ['rows_read' => int, 'balances_count' => int, 'errors' => string[]].
     */
    public function sync(?callable $progress = null): array
    {
        $errors = [];
        $table = preg_replace('/[^a-z0-9_]/i', '', (string) ($this->cfg['register_table'] ?? ''));
        $dimCols = $this->cfg['dimension_columns'] ?? [];
        $qtyCol = $this->sanitizeColumn($this->cfg['quantity_column'] ?? '');
        $amtCol = $this->sanitizeColumn($this->cfg['amount_column'] ?? '');
        $rkCol = $this->sanitizeColumn($this->cfg['recordkind_column'] ?? '_recordkind');

        if ($table === '' || empty($dimCols)) {
            $errors[] = 'Задайте LMB_1C_BALANCES_REGISTER_TABLE и LMB_1C_BALANCES_DIMENSION_COLUMNS в .env (после lmb:1c-balances-discovery).';

            return ['rows_read' => 0, 'balances_count' => 0, 'errors' => $errors];
        }

        $selectParts = [];
        foreach ($dimCols as $col) {
            $c = $this->sanitizeColumn($col);
            if ($c !== '') {
                $selectParts[] = 'encode(r."'.$c.'", \'hex\') as dim_'.preg_replace('/[^a-z0-9_]/i', '_', $c);
            }
        }
        if (empty($selectParts)) {
            $errors[] = 'Нет валидных колонок измерений.';

            return ['rows_read' => 0, 'balances_count' => 0, 'errors' => $errors];
        }

        $selectList = implode(', ', $selectParts);
        if ($qtyCol !== '') {
            $selectList .= ', r."'.$qtyCol.'" as qty';
        }
        if ($amtCol !== '') {
            $selectList .= ', r."'.$amtCol.'" as amt';
        }
        if ($rkCol !== '') {
            $selectList .= ', r."'.$rkCol.'" as rk';
        }

        $sql = "SELECT {$selectList} FROM public.{$table} r";
        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            $errors[] = 'Ошибка чтения регистра 1С: '.$e->getMessage();

            return ['rows_read' => 0, 'balances_count' => 0, 'errors' => $errors];
        }

        $rowsRead = count($rows);
        $balances = [];

        foreach ($rows as $idx => $row) {
            $keyParts = [];
            $raw = [];
            foreach ($dimCols as $col) {
                $c = $this->sanitizeColumn($col);
                if ($c === '') {
                    continue;
                }
                $prop = 'dim_'.preg_replace('/[^a-z0-9_]/i', '_', $c);
                $val = $row->$prop ?? null;
                $str = $val !== null ? (string) $val : '';
                $keyParts[] = $str;
                $raw[$c] = $str;
            }
            $dimensionKey = md5(implode('|', $keyParts));
            if (! isset($balances[$dimensionKey])) {
                $balances[$dimensionKey] = [
                    'dimension_key' => $dimensionKey,
                    'quantity' => 0.0,
                    'amount' => 0.0,
                    'raw_dimensions' => $raw,
                ];
            }

            $recordKind = 1;
            if ($rkCol !== '' && isset($row->rk)) {
                $rk = (int) $row->rk;
                if ($rk === -1 || $rk < 0) {
                    $recordKind = -1;
                } elseif ($rk === 1 || $rk > 0) {
                    $recordKind = 1;
                }
            }

            $qty = $qtyCol !== '' && isset($row->qty) ? (float) $row->qty : 0.0;
            $amt = $amtCol !== '' && isset($row->amt) ? (float) $row->amt : 0.0;
            $balances[$dimensionKey]['quantity'] += $qty * $recordKind;
            $balances[$dimensionKey]['amount'] += $amt * $recordKind;

            if ($progress && ($idx + 1) % 500 === 0) {
                $progress($idx + 1, $rowsRead);
            }
        }

        LmbRegisterBalance::where('register_name', $table)->delete();

        $now = now();
        $inserts = [];
        foreach ($balances as $b) {
            if ($b['quantity'] == 0 && $b['amount'] == 0) {
                continue;
            }
            $inserts[] = [
                'register_name' => $table,
                'dimension_key' => $b['dimension_key'],
                'quantity' => $b['quantity'],
                'amount' => $b['amount'],
                'raw_dimensions' => json_encode($b['raw_dimensions']),
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($inserts)) {
            foreach (array_chunk($inserts, 200) as $chunk) {
                LmbRegisterBalance::insert($chunk);
            }
        }

        return ['rows_read' => $rowsRead, 'balances_count' => count($inserts), 'errors' => $errors];
    }

    private function sanitizeColumn(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/i', '', $name);
    }
}
