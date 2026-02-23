<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API 1С LMB: контрагенты, каталог остатков.
 * Basic Auth: UserWebServis / UserWebServis.
 * - GET {base_url}/user/{phone} — данные контрагента по телефону
 * - GET {base_url}/ostatki — выгрузка каталога товаров (JSON)
 * - POST {base_url}/user_no — создание нового контрагента (JSON body)
 */
class LmbUserApiService
{
    private string $baseUrl;

    private ?string $loginUrl;

    private int $timeout;

    private ?string $username;

    private ?string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.lmb_user_api.base_url', 'http://5.128.186.3:5665/lmb/hs/es'), '/');
        $this->loginUrl = config('services.lmb_user_api.login_url') ? rtrim(config('services.lmb_user_api.login_url'), '/') : null;
        $this->timeout = config('services.lmb_user_api.timeout', 8);
        $this->username = config('services.lmb_user_api.username') ?: null;
        $this->password = config('services.lmb_user_api.password') ?: null;
    }

    /**
     * Нормализовать номер телефона для URL (только цифры).
     */
    public function normalizePhoneForId(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Формат номера для пути GET user/{ID} (по доке 1С: с плюсом для РФ).
     * Пример: 79132083529 → +79132083529 → .../user/+79132083529
     */
    public function formatPhoneForUserUrl(string $phone): string
    {
        $digits = $this->normalizePhoneForId($phone);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '7') && strlen($digits) >= 11) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '8') && strlen($digits) >= 11) {
            return '+' . '7' . substr($digits, 1);
        }
        return $digits;
    }

    /**
     * GET user/{ID} — данные контрагента по телефону (ID = номер с + для РФ).
     * Ответ 1С: user_uid, first_name, second_name, last_name, phone (JSON).
     *
     * @return array<string, mixed>|null
     */
    public function getUserByPhone(string $phone): ?array
    {
        $id = $this->formatPhoneForUserUrl($phone);
        if ($id === '') {
            return null;
        }

        $url = $this->baseUrl . '/user/' . rawurlencode($id);

        try {
            $response = $this->request()->get($url);

            if (! $response->successful()) {
                Log::warning('LmbUserApiService: неуспешный ответ', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $body = $response->body();
            $data = $this->parseResponse($body);
            if ($data !== null && is_array($data)) {
                // Возвращаем сырой ответ 1С (user_uid, first_name, second_name, last_name, phone) для сохранения в карточку
                return $data;
            }

            return ['raw' => $body];
        } catch (\Throwable $e) {
            Log::warning('LmbUserApiService: ошибка запроса', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Выгрузка каталога товаров (остатки) на сайт.
     * GET {base_url}/ostatki → JSON.
     *
     * @return array<int, mixed>|array<string, mixed>|null декодированный JSON или null при ошибке
     */
    public function getOstatki(): ?array
    {
        $url = $this->baseUrl . '/ostatki';

        try {
            $response = $this->request()->get($url);

            if (! $response->successful()) {
                Log::warning('LmbUserApiService: ostatki неуспешный ответ', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = json_decode($response->body(), true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('LmbUserApiService: ostatki ошибка запроса', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Создать нового контрагента в 1С.
     * POST {base_url}/user_no, тело — JSON с данными контрагента.
     *
     * @param  array<string, mixed>  $data  данные контрагента (имя, телефон и т.д. — по формату 1С)
     * @return array<string, mixed>|null ответ API (декодированный JSON) или null при ошибке
     */
    public function createUser(array $data): ?array
    {
        $url = $this->baseUrl . '/user_no';

        try {
            $response = $this->request()
                ->acceptJson()
                ->asJson()
                ->post($url, $data);

            if (! $response->successful()) {
                Log::warning('LmbUserApiService: user_no неуспешный ответ', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $decoded = json_decode($response->body(), true);
            return is_array($decoded) ? $decoded : ['raw' => $response->body()];
        } catch (\Throwable $e) {
            Log::warning('LmbUserApiService: user_no ошибка запроса', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Проверка доступности API (для диагностики).
     * Возвращает: ['ok' => bool, 'status' => int|null, 'message' => string, 'body_preview' => string|null].
     */
    public function testConnection(): array
    {
        $url = $this->baseUrl . '/ostatki';

        try {
            $response = $this->request()->get($url);
            $status = $response->status();
            $body = $response->body();
            $preview = $body !== '' ? mb_substr($body, 0, 200) . (mb_strlen($body) > 200 ? '…' : '') : null;

            return [
                'ok' => $response->successful(),
                'status' => $status,
                'message' => $response->successful() ? 'OK' : "HTTP {$status}",
                'body_preview' => $preview,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'message' => $e->getMessage(),
                'body_preview' => null,
            ];
        }
    }

    /**
     * Проверка доступности API по эндпоинту /user/{phone} (для пинга контрагента).
     * Возвращает ту же структуру, что и testConnection().
     */
    public function testUserConnection(string $phone): array
    {
        $id = $this->formatPhoneForUserUrl($phone);
        if ($id === '') {
            return [
                'ok' => false,
                'status' => null,
                'message' => 'Некорректный номер телефона',
                'body_preview' => null,
            ];
        }
        $url = $this->baseUrl . '/user/' . rawurlencode($id);

        try {
            $response = $this->request()->get($url);
            $status = $response->status();
            $body = $response->body();
            $preview = $body !== '' ? mb_substr($body, 0, 200) . (mb_strlen($body) > 200 ? '…' : '') : null;

            return [
                'ok' => $response->successful(),
                'status' => $status,
                'message' => $response->successful() ? 'OK' : "HTTP {$status}",
                'body_preview' => $preview,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'message' => $e->getMessage(),
                'body_preview' => null,
            ];
        }
    }

    /**
     * HTTP-клиент: при наличии login_url сначала выполняет логин (POST с Basic Auth), затем возвращает клиент с Cookie для последующих запросов.
     * Повтор запроса до 3 раз с паузой 500 мс при сетевой ошибке.
     */
    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        // 2 повтора (всего 3 попытки), пауза 300 мс — укладываемся в max_execution_time 30 сек
        $req = Http::timeout($this->timeout)
            ->acceptJson()
            ->retry(2, 300, function (\Throwable $e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException || str_contains($e->getMessage(), 'cURL error');
            });
        if ($this->username !== null && $this->password !== null) {
            $req = $req->withBasicAuth($this->username, $this->password);
        }

        if ($this->loginUrl !== null && $this->loginUrl !== '') {
            try {
                $loginResponse = $req->post($this->loginUrl);
                if ($loginResponse->successful() || $loginResponse->status() === 302 || $loginResponse->status() === 200) {
                    $cookie = $loginResponse->header('Set-Cookie');
                    if ($cookie !== null && $cookie !== '') {
                        $req = $req->withHeaders(['Cookie' => is_array($cookie) ? implode('; ', $cookie) : $cookie]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('LmbUserApiService: ошибка логина перед запросом', [
                    'login_url' => $this->loginUrl,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $req;
    }

    private function parseResponse(string $body): ?array
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        $first = $body[0] ?? '';
        if ($first === '{') {
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : null;
        }
        if ($first === '<') {
            try {
                $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
                if ($xml !== false) {
                    return json_decode(json_encode($xml), true);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return null;
    }

    /**
     * Привести ответ к единому виду: code, name, phone.
     *
     * @param  array<string, mixed>  $data
     * @return array{code: string, name: string, phone: string}
     */
    private function normalizeUserData(array $data, string $fallbackPhone): array
    {
        $code = (string) ($data['code'] ?? $data['id'] ?? $data['guid'] ?? '');
        $name = (string) ($data['name'] ?? $data['full_name'] ?? $data['firstName'] ?? $data['Имя'] ?? '');
        $phone = (string) ($data['phone'] ?? $data['telephone'] ?? $data['Телефон'] ?? $fallbackPhone);

        return [
            'code' => $code,
            'name' => $name,
            'phone' => $phone,
        ];
    }
}
