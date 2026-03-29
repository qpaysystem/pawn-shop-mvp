<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Вывести список кодов складов/филиалов из документа залога 1С.
 * Нужно для заполнения stores.lmb_store_uid или LMB_1C_PAWN_STORE_MAPPING в .env.
 */
class LmbListPawnStoreRefsCommand extends Command
{
    protected $signature = 'lmb:list-pawn-store-refs
                            {--column= : Колонка документа со ссылкой на склад (переопределяет LMB_1C_PAWN_STORE_COLUMN)}
                            {--json : Вывести маппинг в формате JSON для LMB_1C_PAWN_STORE_MAPPING (hex => store_id по порядку)}
                            {--assign : Сопоставить по порядку с нашими stores и вывести готовый JSON маппинга}';

    protected $description = 'Список кодов складов из документа залога 1С для настройки маппинга store_id';

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только при LMB_DB_DRIVER=pgsql. Проверьте .env.');

            return self::FAILURE;
        }

        $cfg = config('services.lmb_1c_pawn_sync', []);
        $docTable = preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['document_table'] ?? ''));
        $storeCol = $this->option('column') !== null
            ? preg_replace('/[^a-z0-9_]/i', '', (string) $this->option('column'))
            : preg_replace('/[^a-z0-9_]/i', '', (string) ($cfg['store_column'] ?? ''));

        if ($docTable === '') {
            $this->error('Не задана таблица документа залога. Укажите LMB_1C_PAWN_DOCUMENT_TABLE в .env.');

            return self::FAILURE;
        }

        if ($storeCol === '') {
            $this->warn('Не задана колонка склада. Укажите LMB_1C_PAWN_STORE_COLUMN в .env или --column=ИмяКолонки');
            $this->line('Пример: php artisan lmb:list-pawn-store-refs --column=_fld9450rref');

            return self::FAILURE;
        }

        try {
            DB::connection('lmb_1c_pgsql')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения к БД 1С: '.$e->getMessage());

            return self::FAILURE;
        }

        $zeroBytea = '\x00000000000000000000000000000000';
        $sql = "SELECT DISTINCT encode(\"{$storeCol}\", 'hex') AS store_uid
                FROM public.{$docTable}
                WHERE NOT _marked
                  AND \"{$storeCol}\" IS NOT NULL
                  AND \"{$storeCol}\" != '{$zeroBytea}'::bytea
                ORDER BY 1";

        try {
            $rows = DB::connection('lmb_1c_pgsql')->select($sql);
        } catch (\Throwable $e) {
            $this->error('Ошибка запроса: '.$e->getMessage());
            $this->line("Проверьте, что колонка \"{$storeCol}\" есть в таблице {$docTable} (php artisan lmb:db-schema --table={$docTable}).");

            return self::FAILURE;
        }

        $uids = array_map(fn ($r) => strtolower((string) $r->store_uid), $rows);

        if (empty($uids)) {
            $this->warn("В документе {$docTable} не найдено ненулевых значений в колонке {$storeCol}.");

            return self::SUCCESS;
        }

        $this->info('Коды складов в документе залога ('.count($uids).'):');
        $this->table(['store_uid (hex)'], array_map(fn ($u) => [$u], $uids));

        if ($this->option('assign')) {
            $stores = Store::orderBy('id')->get();
            if ($stores->isEmpty()) {
                $this->warn('В таблице stores нет записей. Создайте магазины (Дуси Ковальчук 266/2 и т.д.) и повторите.');

                return self::SUCCESS;
            }
            $mapping = [];
            foreach ($uids as $i => $hex) {
                $store = $stores->get($i);
                $mapping[$hex] = $store ? $store->id : ($i + 1);
            }
            $this->newLine();
            $this->line('Маппинг по порядку (1-й склад 1С → 1-й store, и т.д.). Вставьте в .env:');
            $this->line('LMB_1C_PAWN_STORE_MAPPING='.json_encode($mapping, JSON_UNESCAPED_UNICODE));
            if (count($uids) > $stores->count()) {
                $this->warn('Складов в 1С больше, чем stores у нас — лишним присвоен store_id по индексу. Проверьте и при необходимости создайте магазины.');
            }

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $mapping = array_combine($uids, array_fill(0, count($uids), 1));
            $this->newLine();
            $this->line('Шаблон для .env (замените store_id на нужные):');
            $this->line('LMB_1C_PAWN_STORE_MAPPING='.json_encode($mapping, JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Дальше: заполните stores.lmb_store_uid этими кодами (по одному на магазин) или задайте LMB_1C_PAWN_STORE_MAPPING в .env.');
        $this->line('Варианты: --json (шаблон JSON) или --assign (сопоставить по порядку с нашими stores и вывести готовый маппинг).');

        return self::SUCCESS;
    }
}
