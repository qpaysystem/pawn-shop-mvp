<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Сводка по структуре БД 1С в PostgreSQL: сколько таблиц какого типа, крупнейшие таблицы.
 */
class LmbPostgresStructureOverviewCommand extends Command
{
    protected $signature = 'lmb:postgres-structure-overview
                            {--top=15 : Сколько самых крупных таблиц показать (0 = не показывать)}
                            {--columns : Показать статистику по типовым колонкам (_idrref, _marked)}';

    protected $description = 'Обзор структуры БД 1С (PostgreSQL): категории таблиц, размеры';

    private string $conn = 'lmb_1c_pgsql';

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только для LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        try {
            DB::connection($this->conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Подключение: '.$e->getMessage());

            return self::FAILURE;
        }

        $host = config("database.connections.{$this->conn}.host");
        $db = config("database.connections.{$this->conn}.database");
        $this->info("БД 1С (PostgreSQL): {$host} / {$db}");
        $this->newLine();

        $rows = DB::connection($this->conn)->select("
            SELECT
                CASE
                    WHEN c.relname LIKE '_reference%' AND strpos(c.relname, '_vt') = 0 THEN '1. Справочники (_reference*, без _vt)'
                    WHEN c.relname LIKE '_reference%' AND strpos(c.relname, '_vt') > 0 THEN '2. ТЧ/хранилища справочников (_reference*_vt*)'
                    WHEN c.relname LIKE '_document%' THEN '3. Документы и ТЧ (_document*)'
                    WHEN c.relname LIKE '_enum%' THEN '4. Перечисления (_enum*)'
                    WHEN c.relname LIKE '_infor%' OR c.relname LIKE '_inforeg%' THEN '5. Регистры сведений (_infor*)'
                    WHEN c.relname LIKE '_accum%' THEN '6. Регистры накопления (_accum*)'
                    WHEN c.relname LIKE '_acc%' OR c.relname LIKE '_accrg%' OR c.relname LIKE '_accrgat%' THEN '7. Бухгалтерия (_acc*, _accrg*)'
                    WHEN c.relname LIKE '_const%' OR c.relname LIKE '_constant%' THEN '8. Константы (_const*)'
                    ELSE '9. Прочие'
                END AS category,
                COUNT(*)::bigint AS cnt
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
            GROUP BY 1
            ORDER BY 1
        ");

        $total = 0;
        $tableRows = [];
        foreach ($rows as $r) {
            $total += (int) $r->cnt;
            $tableRows[] = [$r->category, $r->cnt];
        }
        $this->line('<fg=cyan>Таблицы в схеме public по категориям (именование 1С):</>');
        $this->table(['Категория', 'Кол-во таблиц'], $tableRows);
        $this->line('Всего таблиц: <fg=green>'.$total.'</>');
        $this->newLine();

        $top = (int) $this->option('top');
        if ($top > 0) {
            $this->line('<fg=cyan>Крупнейшие таблицы по размеру на диске (heap + индексы, pg_total_relation_size):</>');
            $big = DB::connection($this->conn)->select("
                SELECT c.relname AS table_name,
                       pg_size_pretty(pg_total_relation_size(c.oid)) AS total_size,
                       COALESCE(s.n_live_tup::bigint, 0) AS est_rows
                FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                LEFT JOIN pg_catalog.pg_stat_all_tables s ON s.relid = c.oid
                WHERE n.nspname = 'public' AND c.relkind = 'r'
                ORDER BY pg_total_relation_size(c.oid) DESC
                LIMIT ?
            ", [$top]);
            $bigRows = [];
            foreach ($big as $b) {
                $bigRows[] = [$b->table_name, $b->total_size, $b->est_rows];
            }
            $this->table(['Таблица', 'Размер', 'Строк (оценка, n_live_tup)'], $bigRows);
            $this->newLine();
        }

        if ($this->option('columns')) {
            $this->line('<fg=cyan>Таблицы с типовыми колонками 1С (подсчёт через pg_attribute):</>');
            $idrref = DB::connection($this->conn)->selectOne("
                SELECT COUNT(DISTINCT c.relname)::bigint AS cnt
                FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                JOIN pg_catalog.pg_attribute a ON a.attrelid = c.oid
                WHERE n.nspname = 'public' AND c.relkind = 'r'
                  AND a.attname = '_idrref' AND a.attnum > 0 AND NOT a.attisdropped
            ");
            $marked = DB::connection($this->conn)->selectOne("
                SELECT COUNT(DISTINCT c.relname)::bigint AS cnt
                FROM pg_catalog.pg_class c
                JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                JOIN pg_catalog.pg_attribute a ON a.attrelid = c.oid
                WHERE n.nspname = 'public' AND c.relkind = 'r'
                  AND a.attname = '_marked' AND a.attnum > 0 AND NOT a.attisdropped
            ");
            $this->table(
                ['Признак', 'Таблиц'],
                [
                    ['Имеют колонку _idrref', $idrref->cnt ?? 0],
                    ['Имеют колонку _marked', $marked->cnt ?? 0],
                ]
            );
            $this->newLine();
        }

        $this->line('<fg=yellow>Дальше:</> структура одной таблицы — <comment>php artisan lmb:db-schema --table=ИМЯ</>');
        $this->line('Список по префиксу — <comment>php artisan lmb:db-tables --prefix=_reference --limit=0</>');
        $this->line('Методика анализа — <comment>docs/LMB_1C_POSTGRES_ANALYSIS.md</>');

        return self::SUCCESS;
    }
}
