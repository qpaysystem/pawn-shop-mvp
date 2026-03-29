<?php

namespace App\Console\Commands;

use App\Services\Inventory1cCompareService;
use Illuminate\Console\Command;

/**
 * Сравнение строк залога из Excel-инвентаризации с документом залога в БД 1С и регистром положительных остатков (balance_register).
 */
class LmbCompareInventoryTo1cCommand extends Command
{
    protected $signature = 'lmb:compare-inventory-to-1c
                            {file : Путь к Инвентарка.xls / .xlsx}
                            {--header-row=4 : Строка заголовка колонок}
                            {--sheet=0 : Индекс листа (0-based)}
                            {--samples=20 : Сколько примеров несовпадений показать}';

    protected $description = 'Сравнить инвентаризацию (залог) с документом 1С и регистром остатков';

    public function handle(Inventory1cCompareService $compare): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Нужно LMB_DB_DRIVER=pgsql и доступ к PostgreSQL 1С (lmb_1c_pgsql).');

            return self::FAILURE;
        }

        $path = $this->argument('file');
        if (! is_string($path) || $path === '') {
            $this->error('Укажите путь к файлу.');

            return self::FAILURE;
        }

        $headerRow = (int) $this->option('header-row');
        $sheetIndex = (int) $this->option('sheet');
        $samples = max(1, min(200, (int) $this->option('samples')));

        $result = $compare->compare($path, $headerRow, $sheetIndex, $samples);

        if (! ($result['success'] ?? false)) {
            $this->error($result['error'] ?? 'Ошибка сравнения.');

            return self::FAILURE;
        }

        $this->info('Таблица документа залога: '.($result['doc_table'] ?? ''));
        $this->line('Регистр остатков: '.($result['register_table'] ?? '').' · справочник 252: '.($result['ref252_table'] ?? ''));
        if (! empty($result['register_query_failed'])) {
            $this->warn('Запрос к регистру не выполнился — статистика по остаткам недоступна.');
        }
        $this->newLine();

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Строк залога в Excel', $result['excel_pawn_rows'] ?? 0],
                ['Уникальных ключей номера (Excel)', $result['distinct_doc_keys_excel'] ?? 0],
                ['Строк, документ найден в 1С', $result['doc_found_rows'] ?? 0],
                ['Строк, документ НЕ найден', $result['doc_missing_rows'] ?? 0],
                ['Строк с остатком > 0 в регистре', $result['register_positive_rows'] ?? 0],
                ['Строк: документ есть, остатка в регистре нет', $result['register_miss_rows'] ?? 0],
                ['Строк: регистр не проверялся', $result['register_unknown_rows'] ?? 0],
            ]
        );

        $miss = $result['samples_doc_missing'] ?? [];
        if ($miss !== []) {
            $this->newLine();
            $this->warn('Примеры: документ в Excel не найден в 1С по нормализованному номеру:');
            $this->table(
                ['Документ (Excel)', 'Филиал', 'Наименование'],
                array_map(fn ($r) => [$r['doc'], $r['branch'], mb_substr($r['name'], 0, 60)], $miss)
            );
        }

        $regMiss = $result['samples_register_miss'] ?? [];
        if ($regMiss !== []) {
            $this->newLine();
            $this->warn('Примеры: документ в 1С есть, но нет положительного остатка в регистре (как у фильтра sync):');
            $this->table(
                ['Документ', 'Ключ', '_number 1С', 'Код ref252', 'Наименование Excel'],
                array_map(fn ($r) => [
                    $r['doc'],
                    $r['number_key'],
                    $r['number_1c'],
                    mb_substr($r['ref252_code'], 0, 24),
                    mb_substr($r['name'], 0, 40),
                ], $regMiss)
            );
        }

        $this->newLine();
        $this->line('Товар в 1С для залога с привязкой к строке регистра ищется по справочнику <comment>'.($result['ref252_table'] ?? '252').'</comment> (поле <comment>_code</comment>): оно сопоставляется с номером документа. Наименование из Excel — для ручной проверки; номенклатура в типовой схеме может быть в другой таблице _reference*.');

        return self::SUCCESS;
    }
}
