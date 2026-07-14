<?php

namespace App\Services\Avito;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Клиент Avito Messenger API (OAuth client_credentials). */
class AvitoApiService
{
    private string $base;

    public function __construct()
    {
        $this->base = rtrim((string) config('avito.api_base', 'https://api.avito.ru'), '/');
    }

    /**
     * @return array{ok: bool, error?: string, chats?: array<int, array<string, mixed>>}
     */
    public function listChats(string $userId, int $limit = 50, int $offset = 0): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return ['ok' => false, 'error' => 'Не заданы avito_client_id / avito_client_secret в настройках.'];
        }

        try {
            $response = Http::connectTimeout(10)->timeout(25)
                ->withToken($token)
                ->get("{$this->base}/messenger/v2/accounts/{$userId}/chats", [
                    'limit' => max(1, min(100, $limit)),
                    'offset' => max(0, $offset),
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return $this->decodeList($response, 'chats');
    }

    /**
     * @return array{ok: bool, error?: string, messages?: array<int, array<string, mixed>>}
     */
    public function listMessages(string $userId, string $chatId, int $limit = 50, int $offset = 0): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return ['ok' => false, 'error' => 'Не заданы учётные данные Avito API.'];
        }

        $chatId = rawurlencode($chatId);
        try {
            $response = Http::connectTimeout(10)->timeout(25)
                ->withToken($token)
                ->get("{$this->base}/messenger/v3/accounts/{$userId}/chats/{$chatId}/messages/", [
                    'limit' => max(1, min(100, $limit)),
                    'offset' => max(0, $offset),
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return $this->decodeList($response, 'messages');
    }

    /**
     * @return array{ok: bool, error?: string, message_id?: string}
     */
    public function sendTextMessage(string $userId, string $chatId, string $text): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return ['ok' => false, 'error' => 'Не заданы учётные данные Avito API.'];
        }

        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'Пустое сообщение.'];
        }

        $encodedChat = rawurlencode($chatId);
        try {
            $response = Http::connectTimeout(10)->timeout(25)
                ->withToken($token)
                ->post("{$this->base}/messenger/v1/accounts/{$userId}/chats/{$encodedChat}/messages", [
                    'type' => 'text',
                    'message' => ['text' => mb_substr($text, 0, 3900)],
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => $this->extractError($response->json(), $response->status(), $response->body())];
        }

        $json = $response->json();
        $messageId = null;
        if (is_array($json)) {
            $messageId = $json['id'] ?? ($json['message_id'] ?? ($json['result']['id'] ?? null));
        }

        return ['ok' => true, 'message_id' => $messageId !== null ? (string) $messageId : null];
    }

    /**
     * @return array{ok: bool, error?: string, id?: int, name?: string, email?: string, phone?: string}
     */
    public function getSelfAccount(): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return ['ok' => false, 'error' => 'Не заданы avito_client_id / avito_client_secret.'];
        }

        try {
            $response = Http::connectTimeout(10)->timeout(20)
                ->withToken($token)
                ->get("{$this->base}/core/v1/accounts/self");
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => $this->extractError($response->json(), $response->status(), $response->body())];
        }

        $json = $response->json();
        if (! is_array($json) || ! isset($json['id'])) {
            return ['ok' => false, 'error' => 'Некорректный ответ /core/v1/accounts/self.'];
        }

        return [
            'ok' => true,
            'id' => (int) $json['id'],
            'name' => trim((string) ($json['name'] ?? '')),
            'email' => trim((string) ($json['email'] ?? '')),
            'phone' => trim((string) ($json['phone'] ?? '')),
        ];
    }

    /**
     * Items API: список объявлений авторизованного пользователя.
     * Используем для выгрузки активных объявлений (status=active).
     *
     * @return array{ok: bool, error?: string, resources?: array<int, array<string, mixed>>, meta?: array<string, mixed>}
     */
    public function listItems(string $status = 'active', int $perPage = 100, int $page = 1, ?int $category = null): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return ['ok' => false, 'error' => 'Не заданы avito_client_id / avito_client_secret в настройках.'];
        }

        $query = [
            'status' => $status,
            'per_page' => max(1, min(100, $perPage)),
            'page' => max(1, $page),
        ];
        if ($category) {
            $query['category'] = $category;
        }

        try {
            $response = Http::connectTimeout(10)->timeout(25)
                ->withToken($token)
                ->get("{$this->base}/core/v1/items", $query);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => $this->extractError($response->json(), $response->status(), $response->body())];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['ok' => false, 'error' => 'Некорректный ответ /core/v1/items.'];
        }

        $resources = $json['resources'] ?? ($json['items'] ?? null);
        if (! is_array($resources)) {
            $resources = [];
        }

        $normalized = [];
        foreach ($resources as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return [
            'ok' => true,
            'resources' => $normalized,
            'meta' => is_array($json['meta'] ?? null) ? $json['meta'] : null,
        ];
    }

    public function clearTokenCache(): void
    {
        [$clientId] = $this->clientCredentials();
        if ($clientId !== '') {
            Cache::forget('avito_token_'.hash('sha256', $clientId));
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function clientCredentials(): array
    {
        $clientId = trim((string) Setting::get('avito_client_id', env('AVITO_CLIENT_ID', '')));
        $clientSecret = trim((string) Setting::get('avito_client_secret', env('AVITO_CLIENT_SECRET', '')));

        return [$clientId, $clientSecret];
    }

    private function accessToken(): ?string
    {
        [$clientId, $clientSecret] = $this->clientCredentials();
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $cacheKey = 'avito_token_'.hash('sha256', $clientId);

        return Cache::remember($cacheKey, 3500, function () use ($clientId, $clientSecret) {
            try {
                $response = Http::connectTimeout(10)->timeout(20)
                    ->asForm()
                    ->post("{$this->base}/token", [
                        'grant_type' => 'client_credentials',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('avito_token', ['error' => $e->getMessage()]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('avito_token', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $token = $response->json('access_token');

            return is_string($token) && $token !== '' ? $token : null;
        });
    }

    /**
     * @param  mixed  $json
     */
    private function decodeList(\Illuminate\Http\Client\Response $response, string $listKey): array
    {
        if (! $response->successful()) {
            return ['ok' => false, 'error' => $this->extractError($response->json(), $response->status(), $response->body())];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['ok' => false, 'error' => 'Некорректный ответ Avito API.'];
        }

        $items = $json[$listKey] ?? $json;
        if (! is_array($items)) {
            $items = [];
        }

        /** @var array<int, array<string, mixed>> $normalized */
        $normalized = [];
        foreach ($items as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return ['ok' => true, $listKey => $normalized];
    }

    /**
     * @param  mixed  $json
     */
    private function extractError(mixed $json, int $status, string $body): string
    {
        if ($status === 402) {
            return 'Нужна платная подписка Avito «API мессенджера» для чтения и отправки сообщений.';
        }
        if (is_array($json)) {
            $msg = trim((string) ($json['message'] ?? $json['error'] ?? $json['description'] ?? ''));
            if ($msg !== '') {
                return $msg;
            }
        }

        return trim($body) !== '' ? mb_substr(trim($body), 0, 500) : "Ошибка Avito API (HTTP {$status})";
    }
}
