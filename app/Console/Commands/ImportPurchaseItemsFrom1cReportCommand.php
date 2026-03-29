<?php

namespace App\Console\Commands;

use App\Models\PurchaseContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Импорт «скупленных товаров» из отчёта 1С (таб/tsv из буфера/Excel) и обновление товара в договоре скупки.
 *
 * Важно: в нашей схеме у `purchase_contracts` один `item_id`, а в 1С в одном документе может быть несколько строк.
 * Поэтому при нескольких строках мы сохраняем:
 * - item.name: первое описание (или «Несколько позиций»)
 * - item.description: список всех строк + суммы
 */
class ImportPurchaseItemsFrom1cReportCommand extends Command
{
    protected $signature = 'lmb:import-purchase-items-from-1c-report
                            {path : Путь к файлу (TSV, колонки как в отчёте 1С)}
                            {--dry-run : Только показать, что будет обновлено}';

    protected $description = 'Импортировать описания товаров скупки из отчёта 1С и обновить items у purchase_contracts';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            $this->error('Файл пустой.');

            return self::FAILURE;
        }

        $lines = preg_split('/\\R/u', $raw) ?: [];
        $byDocNumber = []; // docNumber => [ ['desc'=>string,'amount'=>string|null], ... ]

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Ожидаем TSV: дата \t операция+номер+дата \t точка \t клиент \t товар \t сумма ...
            $parts = preg_split('/\\t+/u', $line);
            if (! $parts || count($parts) < 6) {
                continue;
            }

            $op = (string) ($parts[1] ?? '');
            if (mb_stripos($op, 'Скупка') === false) {
                continue;
            }

            if (! preg_match('/\\b(\\d{2}БП-\\d{6})\\b/u', $op, $m)) {
                continue;
            }
            $docNumber = $m[1];

            $desc = trim((string) ($parts[4] ?? ''));
            if ($desc === '') {
                continue;
            }

            $amountRaw = (string) ($parts[5] ?? '');
            $amountRaw = trim($amountRaw);
            $amountRaw = $amountRaw !== '' ? $amountRaw : null;

            $byDocNumber[$docNumber][] = ['desc' => $desc, 'amount' => $amountRaw];
        }

        if (empty($byDocNumber)) {
            $this->warn('В файле не найдено строк «Скупка … 00БП-……».');

            return self::SUCCESS;
        }

        $contracts = PurchaseContract::whereNotNull('lmb_doc_uid')->with('item')->get();
        if ($contracts->isEmpty()) {
            $this->warn('В базе нет purchase_contracts с lmb_doc_uid.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $notFoundInReport = 0;
        $errors = [];

        foreach ($contracts as $contract) {
            if (! $contract->lmb_doc_uid || ! $contract->item) {
                $skipped++;

                continue;
            }

            try {
                $row = DB::connection('lmb_1c_pgsql')->selectOne(
                    "SELECT _number FROM public._document389x1 WHERE _idrref = decode(?, 'hex') LIMIT 1",
                    [$contract->lmb_doc_uid]
                );
                $docNumber = $row?->_number ? (string) $row->_number : null;
                if (! $docNumber) {
                    $skipped++;

                    continue;
                }

                $entries = $byDocNumber[$docNumber] ?? null;
                if (! $entries) {
                    $notFoundInReport++;

                    continue;
                }

                $first = $entries[0]['desc'];
                $name = $first;
                $description = null;

                if (count($entries) > 1) {
                    $name = mb_strlen($first) <= 200 ? ($first.' (и ещё '.(count($entries) - 1).')') : $first;
                    $linesText = [];
                    foreach ($entries as $e) {
                        $s = $e['desc'];
                        if (! empty($e['amount'])) {
                            $s .= ' — '.$e['amount'];
                        }
                        $linesText[] = '- '.$s;
                    }
                    $description = implode("\n", $linesText);
                }

                $payload = [];
                if ($contract->item->name !== $name) {
                    $payload['name'] = $name;
                }
                if ($description !== null && $contract->item->description !== $description) {
                    $payload['description'] = $description;
                }

                $parsed = \App\Services\LmbPurchaseItemNameService::parseItemString($first);
                if ($parsed['metal'] !== null && $contract->item->metal !== $parsed['metal']) {
                    $payload['metal'] = $parsed['metal'];
                }
                if ($parsed['sample'] !== null && $contract->item->sample !== $parsed['sample']) {
                    $payload['sample'] = $parsed['sample'];
                }
                if ($parsed['weight_grams'] !== null && (string) $contract->item->weight_grams !== (string) $parsed['weight_grams']) {
                    $payload['weight_grams'] = $parsed['weight_grams'];
                }

                if (empty($payload)) {
                    $skipped++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line($docNumber.' → item#'.$contract->item->id.': '.json_encode($payload, JSON_UNESCAPED_UNICODE));
                    $updated++;

                    continue;
                }

                $contract->item->update($payload);
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = 'Договор '.$contract->contract_number.': '.$e->getMessage();
            }
        }

        $this->info("Готово. Обновлено товаров: {$updated}, пропущено: {$skipped}, нет в отчёте: {$notFoundInReport}.");

        if ($notFoundInReport > 0 && $updated === 0 && empty($errors)) {
            $reportNumbers = array_keys($byDocNumber);
            $this->newLine();
            $this->comment('В отчёте найдены номера (пример): '.implode(', ', array_slice($reportNumbers, 0, 5)).(count($reportNumbers) > 5 ? ' …' : ''));
            $sampleFromDb = [];
            foreach ($contracts as $c) {
                if (count($sampleFromDb) >= 5) {
                    break;
                }
                $r = DB::connection('lmb_1c_pgsql')->selectOne("SELECT _number FROM public._document389x1 WHERE _idrref = decode(?, 'hex') LIMIT 1", [$c->lmb_doc_uid]);
                if ($r && isset($r->_number)) {
                    $sampleFromDb[] = (string) $r->_number;
                }
            }
            $sampleFromDb = array_unique($sampleFromDb);
            $this->comment('В базе (1С) номера договоров (пример): '.implode(', ', array_slice($sampleFromDb, 0, 5)).(count($sampleFromDb) > 5 ? ' …' : ''));
            $this->comment('Добавьте в TSV строки «Скупка … 00БП-XXXXXX …» с этими номерами, затем запустите команду без --dry-run.');
        }

        if (! empty($errors)) {
            $this->warn('Ошибки:');
            foreach (array_slice($errors, 0, 10) as $err) {
                $this->line('  '.$err);
            }
            if (count($errors) > 10) {
                $this->line('  ... и ещё '.(count($errors) - 10));
            }
        }

        return self::SUCCESS;
    }
}
