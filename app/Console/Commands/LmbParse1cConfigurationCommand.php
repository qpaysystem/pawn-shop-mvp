<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Разбор выгрузки конфигурации 1С (Configuration.xml): извлечение объектов ЛМБ_*
 * для сопоставления с таблицами PostgreSQL и планирования интеграции.
 *
 * Файл может быть большим (миллионы строк) — читается построчно.
 */
class LmbParse1cConfigurationCommand extends Command
{
    protected $signature = 'lmb:parse-1c-configuration
                            {file : Путь к Configuration.xml (выгрузка конфигурации 1С в файл)}
                            {--lmb-only : Только объекты с префиксом ЛМБ_ (по умолчанию)}
                            {--json : Вывести результат в JSON}';

    protected $description = 'Разобрать Configuration.xml 1С и вывести документы/регистры/справочники ЛМБ_*';

    public function handle(): int
    {
        $path = $this->argument('file');
        $lmbOnly = $this->option('lmb-only') ?? true;
        $asJson = $this->option('json');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Файл не найден или недоступен: {$path}");

            return self::FAILURE;
        }

        $this->info("Чтение файла: {$path}");
        $objects = $this->parseFile($path, $lmbOnly);

        if ($asJson) {
            $this->line(json_encode($objects, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if (empty($objects)) {
            $this->warn('Объекты ЛМБ_ не найдены. Проверьте путь к файлу и что это выгрузка конфигурации «Ломбард».');

            return self::SUCCESS;
        }

        $this->table(
            ['Тип', 'Логическое имя', 'Синоним'],
            array_map(fn ($o) => [$o['type'], $o['name'], $o['synonym'] ?? ''], $objects)
        );
        $this->newLine();
        $this->info('Всего: '.count($objects).' объектов. Сопоставление с PostgreSQL см. docs/LMB_1C_METADATA_ANALYSIS.md');

        return self::SUCCESS;
    }

    /**
     * Читает Configuration.xml построчно и извлекает объекты метаданных (ЛМБ_* или все).
     *
     * @return array<int, array{type: string, name: string, synonym?: string}>
     */
    private function parseFile(string $path, bool $lmbOnly): array
    {
        $objects = [];
        $current = null;
        $fh = fopen($path, 'rb');
        if (! $fh) {
            return [];
        }

        $descriptionPattern = '#<Description>([^<]*)</Description>#u';
        $synonymPattern = '#<Синоним>([^<]*)</Синоним>#u';
        $namePattern = '#<Имя>([^<]*)</Имя>#u';

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);

            if (preg_match($descriptionPattern, $line, $m)) {
                $description = trim($m[1]);
                if ($lmbOnly && stripos($description, 'ЛМБ_') === false) {
                    $current = null;

                    continue;
                }
                $pair = $this->parseDescription($description);
                if ($pair === null && $lmbOnly) {
                    $current = null;

                    continue;
                }
                if ($current !== null) {
                    $objects[] = $current;
                }
                $current = $pair ? [
                    'type' => $pair['type'],
                    'name' => $pair['name'],
                    'synonym' => null,
                ] : null;

                continue;
            }

            if ($current !== null && preg_match($synonymPattern, $line, $m)) {
                $current['synonym'] = trim($m[1]);
            }
        }

        if ($current !== null) {
            $objects[] = $current;
        }

        fclose($fh);

        return $this->deduplicateByName($objects);
    }

    /**
     * Убирает дубликаты по имени, оставляя запись с более конкретным типом (document, register, catalog важнее other).
     *
     * @param  array<int, array{type: string, name: string, synonym?: string|null}>  $objects
     * @return array<int, array{type: string, name: string, synonym?: string|null}>
     */
    private function deduplicateByName(array $objects): array
    {
        $byName = [];
        $priority = ['document' => 3, 'accumulation_register' => 3, 'information_register' => 3, 'catalog' => 3, 'other' => 1];
        foreach ($objects as $obj) {
            $name = $obj['name'];
            $p = $priority[$obj['type']] ?? 2;
            if (! isset($byName[$name]) || ($priority[$byName[$name]['type']] ?? 0) < $p) {
                $byName[$name] = $obj;
            } elseif (isset($byName[$name]['synonym']) && ($byName[$name]['synonym'] ?? '') === '' && ($obj['synonym'] ?? '') !== '') {
                $byName[$name]['synonym'] = $obj['synonym'];
            }
        }

        return array_values($byName);
    }

    /**
     * По содержимому тега Description определяет тип объекта и логическое имя.
     *
     * @return array{type: string, name: string}|null
     */
    private function parseDescription(string $description): ?array
    {
        $map = [
            'ДокументСсылка.' => 'document',
            'РегистрНакопленияЗапись.' => 'accumulation_register',
            'РегистрСведенийЗапись.' => 'information_register',
            'СправочникСсылка.' => 'catalog',
            'Справочник_' => 'catalog',
        ];

        foreach ($map as $prefix => $type) {
            $pos = mb_stripos($description, $prefix);
            if ($pos !== false) {
                $name = trim(mb_substr($description, $pos + mb_strlen($prefix)));
                if ($name !== '') {
                    return ['type' => $type, 'name' => $name];
                }
            }
        }

        if (mb_stripos($description, 'ЛМБ_') !== false) {
            return [
                'type' => 'other',
                'name' => $description,
            ];
        }

        return null;
    }
}
