<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** HTTP-клиент OpenAI с опциональным прокси (для серверов в РФ). */
final class OpenAiHttp
{
    public static function client(int $timeout = 120): PendingRequest
    {
        $request = Http::timeout($timeout);
        $proxy = trim((string) config('services.openai.http_proxy', ''));
        if ($proxy !== '') {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        $apiKey = config('services.openai.api_key');
        if ($apiKey !== '' && $apiKey !== null) {
            $request = $request->withToken($apiKey);
        }

        return $request;
    }
}
