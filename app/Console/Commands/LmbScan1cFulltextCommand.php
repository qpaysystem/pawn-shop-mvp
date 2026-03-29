<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Полный скан строковых колонок всех таблиц public в БД 1С (PostgreSQL)
 * по нескольким подстрокам за один проход (быстрее, чем N× lmb:find-passport-in-1c).
 */
class LmbScan1cFulltextCommand extends Command
{
    protected $signature = 'lmb:scan-1c-fulltext
                            {--term=* : Подстрока ILIKE (можно несколько --term=)}
                            {--output= : Путь к файлу отчёта (по умолчанию storage/logs/lmb_1c_fulltext_scan.log)}
                            {--skip-broad : Не добавлять широкие шаблоны 5012 и 999652 (меньше шума)}
                            {--limit-tables=0 : Макс. таблиц (0 = все)}';

    protected $description = 'Полный поиск подстрок по всем строковым колонкам БД 1С (PostgreSQL)';

    private array $stringTypes = [
        'character varying',
        'varchar',
        'text',
        'mvarchar',
        'mchar',
        'char',
    ];

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только для LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        $conn = 'lmb_1c_pgsql';
        $terms = $this->option('term');
        if (! is_array($terms)) {
            $terms = [];
        }
        $terms = array_values(array_filter(array_map('trim', $terms)));

        if ($this->option('skip-broad')) {
            $terms = array_values(array_diff($terms, ['5012', '999652']));
        }

        if ($terms === []) {
            $terms = [
                'Недопрядченко',
                'УФМС',
                'Железнодорож',
                '999652',
                '5012',
                'Отделом УФМС',
                'Новосибирск',
            ];
            if ($this->option('skip-broad')) {
                $terms = array_values(array_diff($terms, ['5012', '999652']));
            }
        }

        $outPath = $this->option('output');
        if (! $outPath) {
            $outPath = storage_path('logs/lmb_1c_fulltext_scan_'.date('Y-m-d_His').'.log');
        }

        $this->info('Подстроки: '.implode(', ', $terms));
        $this->info('Отчёт: '.$outPath);

        try {
            DB::connection($conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Подключение: '.$e->getMessage());

            return self::FAILURE;
        }

        $tablesSql = "
            SELECT n.nspname AS table_schema, c.relname AS table_name
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
            ORDER BY c.relname
        ";
        $tables = DB::connection($conn)->select($tablesSql);
        $limitTables = (int) $this->option('limit-tables');
        if ($limitTables > 0) {
            $tables = array_slice($tables, 0, $limitTables);
        }

        $columnsSql = '
            SELECT a.attname AS column_name, pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
            FROM pg_catalog.pg_attribute a
            WHERE a.attrelid = (SELECT c.oid FROM pg_catalog.pg_class c
                               JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                               WHERE n.nspname = ? AND c.relname = ?)
              AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attnum
        ';

        $fh = fopen($outPath, 'wb');
        if ($fh === false) {
            $this->error('Не удалось открыть файл: '.$outPath);

            return self::FAILURE;
        }

        fwrite($fh, 'lmb:scan-1c-fulltext '.date('c')."\n");
        fwrite($fh, 'terms: '.implode(' | ', $terms)."\n");
        fwrite($fh, 'tables: '.count($tables)."\n\n");

        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        $totalHits = 0;

        foreach ($tables as $row) {
            $schema = $row->table_schema;
            $table = $row->table_name;
            $quotedTable = '"'.str_replace('"', '""', $schema).'"."'.str_replace('"', '""', $table).'"';

            $columns = DB::connection($conn)->select($columnsSql, [$schema, $table]);
            $stringCols = [];
            foreach ($columns as $col) {
                $type = strtolower($col->data_type);
                foreach ($this->stringTypes as $st) {
                    if (str_contains($type, $st) && preg_match('/^[a-zA-Z0-9_]+$/', $col->column_name)) {
                        $stringCols[] = $col->column_name;
                        break;
                    }
                }
            }

            if ($stringCols === []) {
                $bar->advance();

                continue;
            }

            $orParts = [];
            $params = [];
            foreach ($stringCols as $colName) {
                $qc = '"'.str_replace('"', '""', $colName).'"';
                foreach ($terms as $t) {
                    $orParts[] = "{$qc}::text ILIKE ?";
                    $params[] = '%'.$t.'%';
                }
            }

            try {
                $cntRow = DB::connection($conn)->selectOne(
                    'SELECT COUNT(*)::bigint AS c FROM '.$quotedTable.' WHERE ('.implode(' OR ', $orParts).')',
                    $params
                );
                $tableTotal = $cntRow ? (int) $cntRow->c : 0;
            } catch (\Throwable $e) {
                $bar->advance();

                continue;
            }

            if ($tableTotal === 0) {
                $bar->advance();

                continue;
            }

            fwrite($fh, "=== {$schema}.{$table} (строк с любым совпадением: {$tableTotal}) ===\n");

            foreach ($stringCols as $colName) {
                $qc = '"'.str_replace('"', '""', $colName).'"';
                foreach ($terms as $t) {
                    try {
                        $one = DB::connection($conn)->selectOne(
                            "SELECT COUNT(*)::bigint AS c FROM {$quotedTable} WHERE {$qc}::text ILIKE ?",
                            ['%'.$t.'%']
                        );
                        $n = $one ? (int) $one->c : 0;
                        if ($n > 0) {
                            $line = "  {$colName}\t{$t}\t{$n}\n";
                            fwrite($fh, $line);
                            $totalHits++;
                        }
                    } catch (\Throwable $e) {
                        // skip column
                    }
                }
            }
            fwrite($fh, "\n");

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        fwrite($fh, "\nИтого записей (таблица.колонка.термин): {$totalHits}\n");
        fclose($fh);

        $this->info('Готово. Совпадений (строк таблица×колонка×термин): '.$totalHits);
        $this->info('Файл: '.$outPath);

        return self::SUCCESS;
    }
}
