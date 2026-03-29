<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Обнаружение справочников 1С (_reference*): список таблиц, количество записей, примеры наименований.
 * Помогает сопоставить справочники 1С с нашей БД (клиенты, филиалы, сотрудники, номенклатура и подчинённые).
 */
class LmbDiscover1cReferencesCommand extends Command
{
    protected $signature = 'lmb:discover-1c-references
                            {--table= : Одна таблица (например _reference122x1)}
                            {--count : Подсчитать записи в каждой таблице}
                            {--sample=3 : Сколько примеров _description выводить}
                            {--limit=80 : Максимум таблиц в списке (0 = все)}
                            {--only-with-data : Только справочники с записями (NOT _marked)}';

    protected $description = 'Список справочников 1С с примерами наименований для маппинга на нашу БД';

    /** Известные по предыдущему анализу: таблица => подпись */
    private const KNOWN = [
        '_reference122x1' => 'Контрагенты (физлица / залогодатели)',
        '_reference108' => 'Контрагенты (юрлица / банки)',
        '_reference197' => 'Филиалы / точки',
        '_reference224' => 'Пользователи 1С (сотрудники)',
        '_reference121' => 'Сотрудники (?)',
        '_reference140x1' => 'Номенклатура',
        '_reference144' => 'Оборудование (?)',
        '_reference103' => 'Настройки (?)',
        '_reference200' => 'Формы документов (?)',
    ];

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только при LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        $conn = 'lmb_1c_pgsql';
        try {
            DB::connection($conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения к БД 1С: '.$e->getMessage());

            return self::FAILURE;
        }

        $singleTable = $this->option('table');
        $withCount = $this->option('count');
        $sampleSize = max(0, (int) $this->option('sample'));
        $limit = (int) $this->option('limit');
        $onlyWithData = $this->option('only-with-data');

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
        if ($limit > 0) {
            $tables = array_slice($tables, 0, $limit);
        }

        if (empty($tables)) {
            $this->warn('Справочники не найдены.');

            return self::SUCCESS;
        }

        $this->info('Справочники 1С (_reference*): '.count($tables).' таблиц.');
        $this->newLine();

        $hasDescription = null;
        foreach ($tables as $row) {
            $name = $row->table_name;
            $count = null;
            $samples = [];

            if ($withCount || $onlyWithData) {
                try {
                    $count = (int) DB::connection($conn)->table($name)->whereRaw('NOT _marked')->count();
                    if ($onlyWithData && $count === 0) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    $count = null;
                }
            }

            if ($sampleSize > 0) {
                try {
                    if ($this->hasColumn($conn, $name, '_description')) {
                        $rows = DB::connection($conn)->select(
                            "SELECT _description FROM public.{$name} WHERE NOT _marked AND _description IS NOT NULL AND _description <> '' LIMIT ?",
                            [$sampleSize]
                        );
                        $samples = array_map(fn ($r) => mb_substr(trim((string) $r->_description), 0, 50), $rows);
                    }
                } catch (\Throwable $e) {
                    $samples = [];
                }
            }

            $label = self::KNOWN[$name] ?? '';
            $countStr = $count !== null ? (string) $count : '—';
            $sampleStr = implode('; ', $samples);
            if ($sampleStr !== '') {
                $sampleStr = ' | '.$sampleStr;
            }

            $this->line('<fg=cyan>'.$name.'</>'.($label ? " <fg=gray>({$label})</>" : ''));
            if ($withCount) {
                $this->line('  Записей: '.$countStr);
            }
            if ($sampleStr !== '') {
                $this->line('  Примеры: '.$sampleStr);
            }
            $this->newLine();
        }

        $this->line('Известные маппинги см. в docs/LMB_1C_REFERENCES_MAPPING.md. Полный список с подсчётом: php artisan lmb:db-tables --prefix=_reference --no-vt --count.');

        return self::SUCCESS;
    }

    private function hasColumn(string $conn, string $table, string $column): bool
    {
        $r = DB::connection($conn)->selectOne(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
            [$table, $column]
        );

        return $r !== null;
    }
}
