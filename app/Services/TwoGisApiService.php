<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Запрос данных карточки организации в 2ГИС (Places API: рейтинг, отзывы, название). Поддержка нескольких филиалов. */
class TwoGisApiService
{
    /** Возвращает массив данных по всем настроенным филиалам (без null). */
    public function getBranchesInfo(): array
    {
        $key = config('services.dgis.api_key');
        $branchIds = config('services.dgis.branch_ids', []);
        if (empty($key) || empty($branchIds)) {
            return [];
        }

        $result = [];
        foreach ($branchIds as $branchId) {
            $branchId = trim($branchId);
            if ($branchId === '') {
                continue;
            }
            $cacheKey = 'marketing_2gis_branch_'.md5($branchId);
            $info = Cache::remember($cacheKey, now()->addHour(), function () use ($key, $branchId) {
                return $this->fetchBranch($key, $branchId);
            });
            if ($info !== null) {
                $result[] = $info;
            }
        }

        return $result;
    }

    /** Один филиал (первый из списка) — для обратной совместимости. */
    public function getBranchInfo(): ?array
    {
        $branches = $this->getBranchesInfo();

        return $branches[0] ?? null;
    }

    public function fetchBranch(string $apiKey, string $branchId): ?array
    {
        $url = config('services.dgis.api_url', 'https://catalog.api.2gis.com/3.0/items/byid');
        $response = Http::timeout(10)
            ->get($url, [
                'key' => $apiKey,
                'id' => $branchId,
                'locale' => 'ru_RU',
                'fields' => 'items.reviews,items.address,items.address_name,items.full_name,items.name,items.url',
            ]);

        if (! $response->successful()) {
            Log::warning('TwoGisApiService: HTTP '.$response->status(), ['body' => $response->body()]);

            return null;
        }

        $data = $response->json();
        $items = $data['result']['items'] ?? $data['result'] ?? [];
        $item = is_array($items) && isset($items[0]) ? $items[0] : null;
        if (! $item || ! is_array($item)) {
            return null;
        }

        $reviews = $item['reviews'] ?? [];
        $rating = isset($reviews['general_rating']) ? (float) $reviews['general_rating'] : null;
        $reviewsCount = isset($reviews['reviews_count']) ? (int) $reviews['reviews_count'] : (isset($reviews['count']) ? (int) $reviews['count'] : null);
        $name = $item['name'] ?? ($item['full_name'] ?? '');
        $address = $item['address_name'] ?? ($item['full_name'] ?? ($item['address'] ?? ''));
        if (is_array($address)) {
            $address = $address['name'] ?? implode(', ', array_filter($address));
        }
        $link = $item['url'] ?? null;
        if (empty($link) && ! empty($item['id'])) {
            $link = 'https://2gis.ru/firm/'.$item['id'];
        }

        return [
            'name' => $name,
            'address' => $address,
            'rating' => $rating,
            'reviews_count' => $reviewsCount,
            'link' => $link,
            'raw_id' => $item['id'] ?? null,
        ];
    }

    /** Сброс кэша (после обновления настроек или по кнопке «Обновить»). */
    public function clearCache(): void
    {
        $branchIds = config('services.dgis.branch_ids', []);
        foreach ($branchIds as $branchId) {
            if (trim($branchId) !== '') {
                Cache::forget('marketing_2gis_branch_'.md5(trim($branchId)));
            }
        }
    }
}
