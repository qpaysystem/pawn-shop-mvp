<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Вывести список справочников 1С (_reference*) с количеством записей и примерами наименований.
 * Помогает сопоставить таблицы с «Клиенты», «Филиалы», «Сотрудники», «Номенклатура» и подсправочниками.
 */
class LmbListReferenceCatalogsCommand extends Command
{
    protected $signature = 'lmb:list-reference-catalogs
                            {--table= : Только одна таблица (например _reference197)}
                            {--limit=30 : Сколько справочников вывести (0 = все с записями)}
                            {--sample=3 : Сколько примеров _description вывести по каждому}';

    protected $description = 'Список справочников 1С с количеством записей и примерами (для маппинга на клиентов, филиалы, номенклатуру)';

    /** Известные по документации: таблица => краткое назначение */
    private array $known = [
        '_reference122x1' => 'Клиенты (контрагенты — физлица)',
        '_reference108' => 'Контрагенты — юр. лица/банки',
        '_reference197' => 'Филиалы / подразделения',
        '_reference121' => 'Сотрудники',
        '_reference140x1' => 'Номенклатура',
    ];

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только при LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        $singleTable = $this->option('table');
        $limit = (int) $this->option('limit');
        $sampleSize = (int) $this->option('sample');

        $conn = 'lmb_1c_pgsql';
        try {
            DB::connection($conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения к БД 1С: '.$e->getMessage());

            return self::FAILURE;
        }

        $sql = "
            SELECT c.relname AS table_name
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
              AND c.relname LIKE '_reference%'
              AND c.relname NOT LIKE '%\_vt%'
            ORDER BY c.relname
        ";
        $params = [];
        if ($singleTable !== null && $singleTable !== '') {
            $sql = "
                SELECT c.relname AS table_name
                FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relname = ?
            ";
            $params = [$singleTable];
        }

        $tables = DB::connection($conn)->select($sql, $params);

        $hasDescription = null;
        foreach ($tables as $row) {
            $tableName = $row->table_name;
            try {
                $count = DB::connection($conn)->selectOne(
                    "SELECT COUNT(*) AS c FROM public.{$tableName} WHERE NOT _marked"
                );
            } catch (\Throwable $e) {
                $count = (object) ['c' => 0];
            }
            $cnt = (int) ($count->c ?? 0);
            if ($limit > 0 && $cnt === 0 && $singleTable === null) {
                continue;
            }
            $label = $this->known[$tableName] ?? '';
            $this->line('');
            $this->line('<fg=cyan>'.$tableName.'</> '.($label ? "({$label})" : '').' — записей: '.$cnt);

            if ($cnt > 0 && $sampleSize > 0) {
                try {
                    $hasCol = DB::connection($conn)->selectOne(
                        "SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name='_description'",
                        [$tableName]
                    );
                    if ($hasCol) {
                        $samples = DB::connection($conn)->select(
                            "SELECT _description FROM public.{$tableName} WHERE NOT _marked AND _description IS NOT NULL AND _description <> '' LIMIT ?",
                            [$sampleSize]
                        );
                        foreach ($samples as $s) {
                            $this->line('  · '.mb_substr((string) $s->_description, 0, 70));
                        }
                    }
                } catch (\Throwable $e) {
                    $this->line('  (примеры не получены: '.$e->getMessage().')');
                }
            }

            if ($limit > 0 && $singleTable === null) {
                $limit--;
                if ($limit <= 0) {
                    break;
                }
            }
        }

        $this->newLine();
        $this->line('Подробнее: docs/LMB_1C_REFERENCE_CATALOGS.md');

        return self::SUCCESS;
    }
}
