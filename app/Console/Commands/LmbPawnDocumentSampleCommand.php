<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Показать пример строки документа залога из 1С и первой табличной части.
 * Помогает подобрать маппинг колонок для LMB_1C_PAWN_* в .env.
 */
class LmbPawnDocumentSampleCommand extends Command
{
    protected $signature = 'lmb:pawn-document-sample
                            {table : Таблица документа, например _document377 или _document382}
                            {--vt= : Конкретная табличная часть (иначе первая по алфавиту)}';

    protected $description = 'Показать пример записи документа и ТЧ из 1С для настройки синхронизации залогов';

    public function handle(): int
    {
        if (env('LMB_DB_DRIVER') !== 'pgsql') {
            $this->error('Только при LMB_DB_DRIVER=pgsql.');

            return self::FAILURE;
        }

        $conn = 'lmb_1c_pgsql';
        $table = preg_replace('/[^a-z0-9_]/i', '', (string) $this->argument('table'));
        if ($table === '') {
            $this->error('Укажите имя таблицы, например _document377');

            return self::FAILURE;
        }

        try {
            DB::connection($conn)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Ошибка подключения к БД 1С: '.$e->getMessage());

            return self::FAILURE;
        }

        $exists = DB::connection($conn)->selectOne(
            "SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relname = ? AND c.relkind = 'r'",
            [$table]
        );
        if (! $exists) {
            $alt = preg_replace('/x1$/i', '', $table);
            $exists = DB::connection($conn)->selectOne(
                "SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relname = ? AND c.relkind = 'r'",
                [$alt]
            );
            if ($exists) {
                $table = $alt;
                $this->warn("Используется таблица без x1: {$table}");
            } else {
                $this->error("Таблица public.{$table} не найдена.");

                return self::FAILURE;
            }
        }

        $count = DB::connection($conn)->selectOne("SELECT COUNT(*) AS c FROM public.{$table} WHERE NOT _marked");
        $this->info("Таблица: public.{$table}, записей (без пометки): ".($count->c ?? 0));
        if (($count->c ?? 0) === 0) {
            $this->warn('Нет данных для примера. Синхронизировать нечего.');

            return self::SUCCESS;
        }

        $cols = DB::connection($conn)->select(
            "SELECT a.attname, pg_catalog.format_type(a.atttypid, a.atttypmod) AS typ
             FROM pg_attribute a
             WHERE a.attrelid = (SELECT c.oid FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relname = ?)
               AND a.attnum > 0 AND NOT a.attisdropped
             ORDER BY a.attnum",
            [$table]
        );

        $sel = [];
        foreach ($cols as $c) {
            $name = $c->attname;
            $isBytea = stripos($c->typ ?? '', 'bytea') !== false;
            $sel[] = $isBytea ? "encode(\"{$name}\", 'hex') AS \"{$name}\"" : "\"{$name}\"";
        }
        $sql = 'SELECT '.implode(', ', $sel)." FROM public.{$table} WHERE NOT _marked LIMIT 1";
        $row = DB::connection($conn)->selectOne($sql);
        if (! $row) {
            $this->warn('Не удалось прочитать строку.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=cyan>Пример строки документа (первая запись):</>');
        foreach ((array) $row as $k => $v) {
            if ($v === null || $v === '') {
                $v = '<null>';
            } elseif (is_string($v) && strlen($v) > 60) {
                $v = substr($v, 0, 57).'…';
            }
            $this->line("  {$k}: ".(string) $v);
        }

        $vtName = $this->option('vt');
        if ($vtName === null || $vtName === '') {
            $base = preg_replace('/x1$/', '', $table);
            $vtList = DB::connection($conn)->select(
                "SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relname LIKE ? AND c.relname LIKE '%\_vt%' ORDER BY c.relname LIMIT 1",
                [$base.'%']
            );
            $vtName = $vtList[0]->relname ?? null;
        } else {
            $vtName = preg_replace('/[^a-z0-9_]/i', '', $vtName);
        }

        if ($vtName !== null && $vtName !== '') {
            $vtCount = DB::connection($conn)->selectOne("SELECT COUNT(*) AS c FROM public.{$vtName}");
            $this->newLine();
            $this->line('<fg=cyan>Табличная часть: public.'.$vtName.'</> (записей: '.($vtCount->c ?? 0).')');

            $vtCols = DB::connection($conn)->select(
                "SELECT a.attname, pg_catalog.format_type(a.atttypid, a.atttypmod) AS typ
                 FROM pg_attribute a
                 WHERE a.attrelid = (SELECT c.oid FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relname = ?)
                   AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum",
                [$vtName]
            );
            $vtSel = [];
            foreach ($vtCols as $c) {
                $name = $c->attname;
                $isBytea = stripos($c->typ ?? '', 'bytea') !== false;
                $vtSel[] = $isBytea ? "encode(\"{$name}\", 'hex') AS \"{$name}\"" : "\"{$name}\"";
            }
            $vtRow = DB::connection($conn)->selectOne('SELECT '.implode(', ', $vtSel)." FROM public.{$vtName} LIMIT 1");
            if ($vtRow) {
                foreach ((array) $vtRow as $k => $v) {
                    if ($v === null || $v === '') {
                        $v = '<null>';
                    } elseif (is_string($v) && strlen($v) > 60) {
                        $v = substr($v, 0, 57).'…';
                    }
                    $this->line("  {$k}: ".(string) $v);
                }
            }
        }

        $this->newLine();
        $this->line('По этим колонкам задайте в .env: LMB_1C_PAWN_DOCUMENT_TABLE, LMB_1C_PAWN_CONTRAGENT_COLUMN (rref на контрагента), _date_time, _number, сумма займа, срок, ТЧ и колонки ТЧ. Затем: php artisan lmb:sync-pawn-contracts --all --force');

        return self::SUCCESS;
    }
}
