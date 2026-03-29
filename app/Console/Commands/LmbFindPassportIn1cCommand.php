<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Сканирует таблицы БД 1С (PostgreSQL), ищет колонки, в которых встречаются
 * паспортные данные (номер 961613, серия 50 19 / 5019), сопоставляет с полями карточки контрагента.
 */
class LmbFindPassportIn1cCommand extends Command
{
    protected $signature = 'lmb:find-passport-in-1c
                            {--search=961613 : Строка для поиска (номер паспорта или серия)}
                            {--prefix= : Только таблицы с префиксом (_reference, _info, _accum, _document)}
                            {--limit-tables=0 : Макс. таблиц (0 = все)}
                            {--sample : Показать пример строки, где найдено}
                            {--dry-run : Только список таблиц и колонок, без поиска}';

    protected $description = 'Найти в БД 1С таблицы/колонки с паспортными данными (по строке поиска)';

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
        $search = $this->option('search');
        $prefix = $this->option('prefix');
        $limitTables = (int) $this->option('limit-tables');
        $showSample = $this->option('sample');
        $dryRun = $this->option('dry-run');

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
        ";
        $params = [];
        if ($prefix !== null && $prefix !== '') {
            $tablesSql .= ' AND c.relname LIKE ?';
            $params[] = $prefix.'%';
        }
        $tablesSql .= ' ORDER BY c.relname';

        $tables = DB::connection($conn)->select($tablesSql, $params);
        if ($limitTables > 0) {
            $tables = array_slice($tables, 0, $limitTables);
        }

        $this->info('Таблиц к просмотру: '.count($tables));
        if ($dryRun) {
            $this->listTablesAndColumns($conn, $tables);

            return self::SUCCESS;
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

        $found = [];
        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        foreach ($tables as $row) {
            $schema = $row->table_schema;
            $table = $row->table_name;
            $fullTable = $schema.'.'.$table;

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
            if (empty($stringCols)) {
                $bar->advance();

                continue;
            }

            $quotedTable = '"'.str_replace('"', '""', $schema).'"."'.str_replace('"', '""', $table).'"';
            $conditions = [];
            foreach ($stringCols as $colName) {
                $conditions[] = '"'.str_replace('"', '""', $colName).'"::text LIKE ?';
            }
            $where = implode(' OR ', $conditions);
            $params = array_fill(0, count($stringCols), '%'.$search.'%');

            try {
                $cnt = DB::connection($conn)->selectOne("SELECT COUNT(*) AS c FROM {$quotedTable} WHERE ({$where})", $params);
                if ($cnt && (int) $cnt->c > 0) {
                    foreach ($stringCols as $colName) {
                        $quotedCol = '"'.str_replace('"', '""', $colName).'"';
                        $c = DB::connection($conn)->selectOne(
                            "SELECT COUNT(*) AS c FROM {$quotedTable} WHERE {$quotedCol}::text LIKE ?",
                            ['%'.$search.'%']
                        );
                        if ($c && (int) $c->c > 0) {
                            $found[] = [
                                'table' => $fullTable,
                                'column' => $colName,
                                'count' => (int) $c->c,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // skip
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        if (empty($found)) {
            $this->warn("Строка «{$search}» не найдена ни в одной строковой колонке.");

            return self::SUCCESS;
        }

        $this->info('Найдено в колонках:');
        $this->table(['Таблица', 'Колонка', 'Записей'], $found);

        if ($showSample && count($found) > 0) {
            $this->newLine();
            $this->info('Пример строки (первая совпавшая):');
            $first = $found[0];
            [$s, $t] = explode('.', $first['table'], 2);
            $quotedTable = '"'.$s.'"."'.$t.'"';
            $quotedCol = '"'.$first['column'].'"';
            $sample = DB::connection($conn)->selectOne(
                "SELECT * FROM {$quotedTable} WHERE {$quotedCol}::text LIKE ? LIMIT 1",
                ['%'.$search.'%']
            );
            if ($sample) {
                foreach ((array) $sample as $k => $v) {
                    $vs = trim((string) $v);
                    if ($vs !== '' && strlen($vs) < 120) {
                        $this->line("  {$k}: {$vs}");
                    } elseif ($vs !== '') {
                        $this->line("  {$k}: ".substr($vs, 0, 80).'…');
                    }
                }
            }
        }

        $this->newLine();
        $this->printScreenshotMapping($found);

        return self::SUCCESS;
    }

    private function listTablesAndColumns(string $conn, array $tables): void
    {
        $columnsSql = '
            SELECT a.attname AS column_name, pg_catalog.format_type(a.atttypid, a.atttypmod) AS data_type
            FROM pg_catalog.pg_attribute a
            WHERE a.attrelid = (SELECT c.oid FROM pg_catalog.pg_class c
                               JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
                               WHERE n.nspname = ? AND c.relname = ?)
              AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attnum
        ';

        foreach ($tables as $row) {
            $this->line($row->table_schema.'.'.$row->table_name);
            $columns = DB::connection($conn)->select($columnsSql, [$row->table_schema, $row->table_name]);
            foreach ($columns as $col) {
                $this->line('  '.$col->column_name.' '.$col->data_type);
            }
        }
    }

    private function printScreenshotMapping(array $found): void
    {
        $this->info('Сопоставление с полями карточки контрагента (скриншот):');
        $this->line('  На форме 1С              → Таблица.колонка (из текущего поиска или ранее известные)');
        $this->line('  ─────────────────────────────────────────────────────────────────────────────');
        $this->line('  ФИО / Наименование       → public._reference122x1._fld3178');
        $this->line('  Телефон 1                 → public._reference122x1._fld41084');
        $this->line('  Дата рождения             → public._reference122x1._fld3191');
        $this->line('  Серия паспорта            → _fld3202 или _fld3184 (в этой базе часто пусто у физлиц)');
        $this->line('  Номер паспорта            → _fld3201 или _fld3185');
        if (count($found) > 0) {
            $this->line('  Найденные колонки с «'.$this->option('search').'»:');
            foreach ($found as $f) {
                $this->line('    → '.$f['table'].'.'.$f['column'].' ('.$f['count'].' зап.)');
            }
        }
    }
}
