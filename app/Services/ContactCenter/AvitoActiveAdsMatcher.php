<?php

namespace App\Services\ContactCenter;

use App\Models\Item;
use App\Models\ItemStatus;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use App\Services\Avito\AvitoApiService;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Матчинг "активные объявления Avito" ↔ товары портала по названию.
 *
 * Источник Avito сейчас ручной, поэтому используем выгрузку из кабинета (xlsx/csv).
 */
class AvitoActiveAdsMatcher
{
    public function __construct(
        private AvitoApiService $avitoApi = new AvitoApiService(),
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   warnings?: list<string>,
     *   ads?: list<array{title:string, price?:float, status?:string, url?:string, id?:string}>,
     *   matches?: list<array{
     *     ad: array{title:string, price?:float, status?:string, url?:string, id?:string},
     *     best?: array{item_id:int, item_name:string, barcode:string, score:int, current_price?:float},
     *     candidates?: list<array{item_id:int, item_name:string, barcode:string, score:int, current_price?:float}}
     *   }>,
     *   items_count?: int
     * }
     */
    public function match(UploadedFile $file, int $storeId, int $statusId): array
    {
        $warnings = [];

        $store = Store::query()->find($storeId);
        if (! $store) {
            return ['ok' => false, 'error' => 'Точка не найдена.'];
        }

        if (! ItemStatus::query()->whereKey($statusId)->exists()) {
            return ['ok' => false, 'error' => 'В системе не найден выбранный статус товара.'];
        }

        $items = Item::query()
            ->where('store_id', $storeId)
            ->where('status_id', (int) $statusId)
            ->get(['id', 'name', 'barcode', 'current_price']);

        $adsResult = $this->parseAdsFile($file->getPathname(), $warnings);
        if (! $adsResult['ok']) {
            return $adsResult;
        }

        /** @var list<array{title:string, price?:float, status?:string, url?:string, id?:string}> $ads */
        $ads = $adsResult['ads'] ?? [];
        if ($ads === []) {
            return ['ok' => false, 'error' => 'Не нашли строк объявлений в файле. Проверьте формат выгрузки Avito.'];
        }

        $itemsNorm = [];
        foreach ($items as $it) {
            $itemsNorm[] = [
                'id' => (int) $it->id,
                'name' => (string) $it->name,
                'barcode' => (string) ($it->barcode ?? ''),
                'current_price' => $it->current_price !== null ? (float) $it->current_price : null,
                'norm' => $this->norm((string) $it->name),
            ];
        }

        $matches = [];
        foreach ($ads as $ad) {
            $normTitle = $this->norm((string) ($ad['title'] ?? ''));
            $scored = [];
            foreach ($itemsNorm as $it) {
                $score = $this->similarityScore($normTitle, $it['norm']);
                if ($score <= 0) {
                    continue;
                }
                $scored[] = [
                    'item_id' => $it['id'],
                    'item_name' => $it['name'],
                    'barcode' => $it['barcode'],
                    'current_price' => $it['current_price'],
                    'score' => $score,
                ];
            }

            usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
            $candidates = array_slice($scored, 0, 3);

            $matches[] = [
                'ad' => $ad,
                'best' => $candidates[0] ?? null,
                'candidates' => $candidates,
            ];
        }

        return [
            'ok' => true,
            'warnings' => $warnings,
            'ads' => $ads,
            'matches' => $matches,
            'items_count' => $items->count(),
        ];
    }

    /**
     * Забрать активные объявления через Avito API и сопоставить с витриной точки.
     *
     * @return array{ok: bool, error?: string, warnings?: list<string>, matches?: list<array<string, mixed>>, items_count?: int}
     */
    public function matchFromApi(int $storeId, int $statusId): array
    {
        $warnings = [];

        $store = Store::query()->find($storeId);
        if (! $store) {
            return ['ok' => false, 'error' => 'Точка не найдена.'];
        }

        if (! ItemStatus::query()->whereKey($statusId)->exists()) {
            return ['ok' => false, 'error' => 'В системе не найден выбранный статус товара.'];
        }

        $items = Item::query()
            ->where('store_id', $storeId)
            ->where('status_id', (int) $statusId)
            ->get(['id', 'name', 'barcode', 'current_price']);

        $ads = [];
        $page = 1;
        while (true) {
            $res = $this->avitoApi->listItems('active', 100, $page);
            if (! ($res['ok'] ?? false)) {
                return ['ok' => false, 'error' => $res['error'] ?? 'Не удалось получить объявления Avito.'];
            }
            $batch = $res['resources'] ?? [];
            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $ad = ['title' => $title];
                if (isset($row['id'])) {
                    $ad['id'] = (string) $row['id'];
                }
                if (isset($row['status'])) {
                    $ad['status'] = (string) $row['status'];
                }
                if (isset($row['url'])) {
                    $ad['url'] = (string) $row['url'];
                }
                if (isset($row['price'])) {
                    $ad['price'] = is_numeric($row['price']) ? (float) $row['price'] : null;
                }
                $ads[] = $ad;
            }

            // safety: не крутим бесконечно
            $page++;
            if ($page > 50) {
                $warnings[] = 'Остановились на 50 страницах Avito API (защита от бесконечного пагинатора).';
                break;
            }
        }

        if ($ads === []) {
            return ['ok' => false, 'error' => 'Avito API вернул пустой список активных объявлений (status=active).'];
        }

        // переиспользуем ту же логику матчинга
        $itemsNorm = [];
        foreach ($items as $it) {
            $itemsNorm[] = [
                'id' => (int) $it->id,
                'name' => (string) $it->name,
                'barcode' => (string) ($it->barcode ?? ''),
                'current_price' => $it->current_price !== null ? (float) $it->current_price : null,
                'norm' => $this->norm((string) $it->name),
            ];
        }

        $matches = [];
        foreach ($ads as $ad) {
            $normTitle = $this->norm((string) ($ad['title'] ?? ''));
            $scored = [];
            foreach ($itemsNorm as $it) {
                $score = $this->similarityScore($normTitle, $it['norm']);
                if ($score <= 0) {
                    continue;
                }
                $scored[] = [
                    'item_id' => $it['id'],
                    'item_name' => $it['name'],
                    'barcode' => $it['barcode'],
                    'current_price' => $it['current_price'],
                    'score' => $score,
                ];
            }

            usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
            $candidates = array_slice($scored, 0, 3);

            $matches[] = [
                'ad' => $ad,
                'best' => $candidates[0] ?? null,
                'candidates' => $candidates,
            ];
        }

        return [
            'ok' => true,
            'warnings' => $warnings,
            'matches' => $matches,
            'items_count' => $items->count(),
        ];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{ok: bool, error?: string, ads?: list<array{title:string, price?:float, status?:string, url?:string, id?:string}>}
     */
    private function parseAdsFile(string $path, array &$warnings): array
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Не удалось прочитать файл (xlsx/csv): '.$e->getMessage()];
        }

        $highestRow = min(5000, (int) $sheet->getHighestRow());
        $highestCol = (string) $sheet->getHighestDataColumn();

        // Ищем строку заголовков (эвристика по ключевым словам).
        $headerRow = 1;
        $bestHits = 0;
        for ($r = 1; $r <= min($highestRow, 50); $r++) {
            $rowStr = mb_strtolower(trim((string) $sheet->rangeToArray('A'.$r.':'.$highestCol.$r, null, true, false)[0][0] ?? ''));
            $hits = 0;
            foreach (['назван', 'заголов', 'title', 'price', 'цена', 'статус', 'status', 'ссыл', 'url', 'id'] as $kw) {
                if ($rowStr !== '' && str_contains($rowStr, $kw)) {
                    $hits++;
                }
            }
            if ($hits > $bestHits) {
                $bestHits = $hits;
                $headerRow = $r;
            }
        }

        $headers = $sheet->rangeToArray('A'.$headerRow.':'.$highestCol.$headerRow, null, true, false)[0] ?? [];
        $map = $this->detectColumns($headers);
        if (! isset($map['title'])) {
            return ['ok' => false, 'error' => 'Не удалось определить колонку с названием объявления. Нужна колонка вроде “Название/Заголовок/Title”.'];
        }

        $ads = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $row = $sheet->rangeToArray('A'.$r.':'.$highestCol.$r, null, true, false)[0] ?? [];
            $title = $this->cellString($row[$map['title']] ?? null);
            if ($title === '') {
                continue;
            }

            $ad = ['title' => $title];
            if (isset($map['price'])) {
                $p = $this->cellString($row[$map['price']] ?? null);
                if ($p !== '' && preg_match('/([\d\s]+[.,]?\d*)/u', $p, $m)) {
                    $ad['price'] = (float) str_replace([' ', ','], ['', '.'], $m[1]);
                }
            }
            if (isset($map['status'])) {
                $st = $this->cellString($row[$map['status']] ?? null);
                if ($st !== '') {
                    $ad['status'] = $st;
                }
            }
            if (isset($map['url'])) {
                $u = $this->cellString($row[$map['url']] ?? null);
                if ($u !== '') {
                    $ad['url'] = $u;
                }
            }
            if (isset($map['id'])) {
                $id = $this->cellString($row[$map['id']] ?? null);
                if ($id !== '') {
                    $ad['id'] = $id;
                }
            }

            $ads[] = $ad;
        }

        if ($bestHits < 2) {
            $warnings[] = 'Заголовки колонок определены эвристически. Если матчинг странный — пришлите пример выгрузки, подгоним парсер под формат Avito.';
        }

        return ['ok' => true, 'ads' => $ads];
    }

    /** @param list<mixed> $headers @return array<string, int> */
    private function detectColumns(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $name = mb_strtolower(trim((string) $h));
            if ($name === '') {
                continue;
            }
            if (! isset($map['title']) && (str_contains($name, 'назван') || str_contains($name, 'заголов') || $name === 'title')) {
                $map['title'] = $i;
                continue;
            }
            if (! isset($map['price']) && (str_contains($name, 'цена') || str_contains($name, 'price'))) {
                $map['price'] = $i;
                continue;
            }
            if (! isset($map['status']) && (str_contains($name, 'статус') || str_contains($name, 'status'))) {
                $map['status'] = $i;
                continue;
            }
            if (! isset($map['url']) && (str_contains($name, 'ссыл') || str_contains($name, 'url'))) {
                $map['url'] = $i;
                continue;
            }
            if (! isset($map['id']) && ($name === 'id' || str_contains($name, 'id объяв') || str_contains($name, 'номер объяв'))) {
                $map['id'] = $i;
                continue;
            }
        }

        return $map;
    }

    private function cellString(mixed $v): string
    {
        $s = trim((string) ($v ?? ''));
        return $s;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';
        return trim($s);
    }

    /**
     * Быстрый скор похожести 0..100.
     * similar_text довольно дорогой, но на наших объёмах (до пары тысяч строк) норм.
     */
    private function similarityScore(string $a, string $b): int
    {
        if ($a === '' || $b === '') {
            return 0;
        }
        // Быстрый отсев.
        if (abs(mb_strlen($a) - mb_strlen($b)) > 80) {
            return 0;
        }
        $pct = 0.0;
        similar_text($a, $b, $pct);
        return (int) round($pct);
    }
}

