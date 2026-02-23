<?php

namespace App\Services;

use App\Models\Setting;

class TelegramAuthService
{
    /**
     * Проверяет данные авторизации из Telegram Login Widget.
     * @see https://core.telegram.org/widgets/login#checking-authorization
     */
    public static function verifyAuthData(array $data): bool
    {
        $hash = $data['hash'] ?? null;
        if (!$hash) {
            return false;
        }
        unset($data['hash']);
        ksort($data);
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);
        $secretKey = hash('sha256', Setting::get('telegram_bot_token', ''), true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        return hash_equals($calculatedHash, $hash);
    }
}
