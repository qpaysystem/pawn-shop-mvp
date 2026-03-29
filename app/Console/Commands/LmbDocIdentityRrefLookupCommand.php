<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * По данным БД 1С: куда ссылаются _fld3222rref и _fld3223rref из хранилища
 * «Документ удостоверяющий личность» контрагентов (_reference122_vt3220x1).
 * Ищет таблицы (_reference*, _enum*), в которых встречается значение ссылки как _idrref.
 */
class LmbDocIdentityRrefLookupCommand extends Command
{
    protected $signature = 'lmb:doc-identity-rref-lookup
                            {--sample=10 : Сколько разных значений _fld3222rref/_fld3223rref взять для поиска}';

    protected $description = 'Найти по данным БД: в какие таблицы ссылаются _fld3222rref и _fld3223rref (вид документа залогодателя)';

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

        $sample = (int) $this->option('sample');
        if ($sample < 1) {
            $sample = 10;
        }

        $this->info('Проверка: куда ссылаются _fld3222rref и _fld3223rref из _reference122_vt3220x1');
        $this->newLine();

        // Проверяем наличие колонок
        $has3222 = $this->columnExists('_reference122_vt3220x1', '_fld3222rref');
        $has3223 = $this->columnExists('_reference122_vt3220x1', '_fld3223rref');
        if (! $has3222 && ! $has3223) {
            $this->warn('В _reference122_vt3220x1 нет колонок _fld3222rref и _fld3223rref.');

            return self::SUCCESS;
        }

        // Собираем уникальные непустые значения ссылок (в hex для удобства и поиска)
        $samples3222 = [];
        $samples3223 = [];
        if ($has3222) {
            $rows = DB::connection($this->conn)->select("
                SELECT DISTINCT encode(\"_fld3222rref\", 'hex') AS rref_hex
                FROM public._reference122_vt3220x1
                WHERE \"_fld3222rref\" IS NOT NULL AND octet_length(\"_fld3222rref\") > 0
                LIMIT ?
            ", [$sample]);
            foreach ($rows as $r) {
                $samples3222[] = $r->rref_hex;
            }
        }
        if ($has3223) {
            $rows = DB::connection($this->conn)->select("
                SELECT DISTINCT encode(\"_fld3223rref\", 'hex') AS rref_hex
                FROM public._reference122_vt3220x1
                WHERE \"_fld3223rref\" IS NOT NULL AND octet_length(\"_fld3223rref\") > 0
                LIMIT ?
            ", [$sample]);
            foreach ($rows as $r) {
                $samples3223[] = $r->rref_hex;
            }
        }

        $this->line('<fg=cyan>Найдено уникальных значений: _fld3222rref = '.count($samples3222).', _fld3223rref = '.count($samples3223).'</>');
        if (empty($samples3222) && empty($samples3223)) {
            $this->warn('Нет непустых ссылок в хранилище. Возможно, вид документа хранится в строковых полях (_fld3224 и др.).');

            return self::SUCCESS;
        }
        $this->newLine();

        // Показать примеры значений ссылок (hex)
        if (! empty($samples3222)) {
            $this->line('<fg=cyan>Примеры _fld3222rref (hex):</>');
            foreach (array_slice($samples3222, 0, 3) as $hex) {
                $this->line('  '.$hex);
            }
            $this->newLine();
        }
        if (! empty($samples3223)) {
            $this->line('<fg=cyan>Примеры _fld3223rref (hex):</>');
            foreach (array_slice($samples3223, 0, 3) as $hex) {
                $this->line('  '.$hex);
            }
            $this->newLine();
        }

        // Таблицы-кандидаты: перечисления и справочники (вид документа часто — перечисление)
        $candidateTables = DB::connection($this->conn)->select("
            SELECT n.nspname AS schema_name, c.relname AS table_name,
                   (SELECT a.attname FROM pg_catalog.pg_attribute a
                    WHERE a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
                      AND pg_catalog.format_type(a.atttypid, a.atttypmod) = 'bytea'
                    ORDER BY CASE a.attname WHEN '_idrref' THEN 0 WHEN '_keyfield' THEN 1 ELSE 2 END, a.attnum
                    LIMIT 1) AS bytea_column
            FROM pg_catalog.pg_class c
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
              AND c.relname LIKE '_enum%'
              AND EXISTS (
                SELECT 1 FROM pg_catalog.pg_attribute a
                WHERE a.attrelid = c.oid AND a.attname = '_idrref' AND a.attnum > 0 AND NOT a.attisdropped
              )
            ORDER BY c.relname
        ");

        $candidateTables = array_filter($candidateTables, fn ($t) => $t->bytea_column !== null);
        $this->line('Поиск в таблицах _enum* (перечисления, колонка _idrref): '.count($candidateTables).' таблиц.');
        $this->newLine();

        $unionSql = $this->buildEnumUnionExistsSql($candidateTables);
        if ($unionSql === '') {
            $this->warn('Нет таблиц _enum* с _idrref.');

            return self::SUCCESS;
        }

        $found3222 = [];
        $found3223 = [];
        foreach ($samples3222 as $hex) {
            if ($hex === '00000000000000000000000000000000') {
                continue;
            }
            foreach ($this->findTablesInUnion($unionSql, $hex) as $tbl) {
                $found3222[$tbl.'._idrref'] = ($found3222[$tbl.'._idrref'] ?? 0) + 1;
            }
        }
        foreach ($samples3223 as $hex) {
            if ($hex === '00000000000000000000000000000000') {
                continue;
            }
            foreach ($this->findTablesInUnion($unionSql, $hex) as $tbl) {
                $found3223[$tbl.'._idrref'] = ($found3223[$tbl.'._idrref'] ?? 0) + 1;
            }
        }

        $this->line('<fg=cyan>Результат: в каких таблицах встречаются значения _fld3222rref / _fld3223rref</>');
        $this->newLine();

        if (! empty($found3222)) {
            $this->line('<fg=yellow>_fld3222rref → ссылается на таблицу.колонку (вид документа или компонент типа):</>');
            arsort($found3222);
            foreach ($found3222 as $tableCol => $cnt) {
                $this->line('  '.$tableCol.'  (совпадений: '.$cnt.')');
            }
            $this->newLine();
        } else {
            $this->line('_fld3222rref: ни в одной таблице _enum* (по _idrref) не найдено совпадений.');
            $this->newLine();
        }

        if (! empty($found3223)) {
            $this->line('<fg=yellow>_fld3223rref → ссылается на таблицу (перечисление):</>');
            arsort($found3223);
            foreach ($found3223 as $tableCol => $cnt) {
                $this->line('  '.$tableCol.'  (совпадений: '.$cnt.')');
            }
            $this->newLine();
        } else {
            $this->line('_fld3223rref: ни в одной таблице _enum* (по _idrref) не найдено совпадений.');
            $this->newLine();
        }

        // Показать примеры записей из первой найденной таблицы (название вида документа)
        $showKey = array_key_first($found3222 ?: $found3223 ?: []);
        if ($showKey !== null) {
            $parts = explode('.', $showKey);
            $tableOnly = (count($parts) >= 2) ? $parts[1] : $parts[0];
            $this->line('<fg=cyan>Примеры записей из «'.$tableOnly.'» (возможные значения «вид документа»):</>');
            $this->showSampleRecords($tableOnly, 15);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, object{schema_name: string, table_name: string}>  $candidateTables
     */
    private function buildEnumUnionExistsSql(array $candidateTables): string
    {
        $parts = [];
        foreach ($candidateTables as $t) {
            $full = $t->schema_name.'.'.$t->table_name;
            $parts[] = "SELECT '{$full}' AS tbl WHERE EXISTS (SELECT 1 FROM {$full} WHERE \"_idrref\" = decode(?, 'hex'))";
        }

        return implode("\nUNION ALL\n", $parts);
    }

    /** @return string[] */
    private function findTablesInUnion(string $unionSql, string $hex): array
    {
        try {
            $paramCount = substr_count($unionSql, 'decode(?,');
            $params = array_fill(0, $paramCount, $hex);
            $rows = DB::connection($this->conn)->select($unionSql, $params);
            $out = [];
            foreach ($rows as $r) {
                if (! empty($r->tbl)) {
                    $out[] = $r->tbl;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            $this->warn('Ошибка пакетного поиска: '.$e->getMessage());

            return [];
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $r = DB::connection($this->conn)->selectOne("
            SELECT 1 FROM pg_catalog.pg_attribute a
            JOIN pg_catalog.pg_class c ON c.oid = a.attrelid
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relname = ? AND a.attname = ?
              AND a.attnum > 0 AND NOT a.attisdropped
        ", [$table, $column]);

        return $r !== null;
    }

    private function showSampleRecords(string $tableName, int $limit): void
    {
        // У справочников обычно есть _description или _fld с наименованием
        $cols = DB::connection($this->conn)->select("
            SELECT a.attname
            FROM pg_catalog.pg_attribute a
            JOIN pg_catalog.pg_class c ON c.oid = a.attrelid
            JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relname = ?
              AND a.attnum > 0 AND NOT a.attisdropped
            ORDER BY a.attnum
        ", [$tableName]);

        $selectCols = [];
        foreach ($cols as $c) {
            $name = $c->attname;
            if ($name === '_idrref') {
                $selectCols[] = "encode(\"_idrref\", 'hex') AS _idrref";
            } elseif (in_array($name, ['_description', '_fld3178'], true) || preg_match('/^_fld\d+$/', $name)) {
                $selectCols[] = "trim(\"{$name}\"::text) AS \"{$name}\"";
            }
        }
        if (empty($selectCols)) {
            $selectCols = ['encode("_idrref", \'hex\') AS _idrref'];
        }
        $selectList = implode(', ', array_slice($selectCols, 0, 5));
        try {
            $hasMarked = $this->columnExists($tableName, '_marked');
            $where = $hasMarked ? 'WHERE NOT COALESCE(_marked, false)' : '';
            $rows = DB::connection($this->conn)->select("
                SELECT {$selectList}
                FROM public.{$tableName}
                {$where}
                LIMIT ".(int) $limit
            );
            foreach ($rows as $r) {
                $this->line('  '.json_encode((array) $r, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
            $this->warn('  Не удалось прочитать примеры: '.$e->getMessage());
        }
    }
}
