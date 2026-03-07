<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Запрос данных карточки организации в 2ГИС (Places API: рейтинг, отзывы, название). */
class TwoGisApiService
{
    public function getBranchInfo(): ?array
    {
        $key = config('services.dgis.api_key');
        $branchId = config('services.dgis.branch_id');
        if (empty($key) || empty($branchId)) {
            return null;
        }

        $cacheKey = 'marketing_2gis_branch_' . md5($branchId);
        return Cache::remember($cacheKey, now()->addHour(), function () use ($key, $branchId) {
            return $this->fetchBranch($key, $branchId);
        });
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
            Log::warning('TwoGisApiService: HTTP ' . $response->status(), ['body' => $response->body()]);
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
            $link = 'https://2gis.ru/firm/' . $item['id'];
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
        $branchId = config('services.dgis.branch_id');
        if ($branchId) {
            Cache::forget('marketing_2gis_branch_' . md5($branchId));
        }
    }
}
