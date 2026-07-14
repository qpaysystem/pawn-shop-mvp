<?php

namespace App\Support;

use App\Models\Setting;

/** Учётные данные info.acuerdo.pro: БД (настройки) → .env */
final class AcuerdoCredentials
{
    public static function username(): string
    {
        $fromDb = trim((string) Setting::get('acuerdo_username', ''));

        return $fromDb !== '' ? $fromDb : trim((string) config('services.acuerdo.username', ''));
    }

    public static function password(): string
    {
        $fromDb = trim((string) Setting::get('acuerdo_password', ''));

        return $fromDb !== '' ? $fromDb : trim((string) config('services.acuerdo.password', ''));
    }

    public static function isConfigured(): bool
    {
        return self::username() !== '' && self::password() !== '';
    }
}
