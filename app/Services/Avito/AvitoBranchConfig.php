<?php

namespace App\Services\Avito;

use App\Models\Setting;

/** Филиалы Авито: user_id аккаунта на каждую точку. */
class AvitoBranchConfig
{
    /**
     * @return array<string, array{slug: string, label: string, user_id: ?string, store_hint: string}>
     */
    public static function branches(): array
    {
        $defaults = (array) config('avito.branch_defaults', []);
        $overrides = self::decodedBranchesSetting();
        $out = [];

        foreach ($defaults as $slug => $meta) {
            if (! is_string($slug) || ! is_array($meta)) {
                continue;
            }
            $ov = is_array($overrides[$slug] ?? null) ? $overrides[$slug] : [];
            $userId = trim((string) ($ov['user_id'] ?? ''));
            $out[$slug] = [
                'slug' => $slug,
                'label' => trim((string) ($ov['label'] ?? $meta['label'] ?? $slug)),
                'user_id' => $userId !== '' ? $userId : null,
                'store_hint' => (string) ($meta['store_hint'] ?? $slug),
            ];
        }

        return $out;
    }

    public static function branch(string $slug): ?array
    {
        return self::branches()[$slug] ?? null;
    }

    public static function isConfigured(): bool
    {
        if (trim((string) Setting::get('avito_client_id', '')) === '') {
            return false;
        }
        if (trim((string) Setting::get('avito_client_secret', '')) === '') {
            return false;
        }
        foreach (self::branches() as $branch) {
            if (! empty($branch['user_id'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function decodedBranchesSetting(): array
    {
        $raw = Setting::get('avito_branches', '');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
