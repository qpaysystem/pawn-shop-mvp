<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Загрузка истории звонков из MTS VPBX (vpbx.mts.ru).
 * API CRM: токен в заголовке X-AUTH-TOKEN, история — GET /api/v1/callHistory/enterprise.
 */
class MtsVpbxService
{
    private string $baseUrl;

    private string $login;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.mts_vpbx.url', 'https://vpbx.mts.ru'), '/');
        $this->login = config('services.mts_vpbx.login', '');
        $this->token = config('services.mts_vpbx.password', '');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    /**
     * Загрузить список звонков за период (API: GET /api/v1/callHistory/enterprise).
     * Возвращает массив: [ ['external_id' => ..., 'contact_date' => ..., 'contact_phone' => ..., 'direction' => ..., 'notes' => ..., 'call_status' => placed|missed, 'ext_tracking_id' => ...], ... ]
     *
     * @param  \DateTimeInterface  $dateFrom
     * @param  \DateTimeInterface  $dateTo
     * @return array<int, array{external_id: string, contact_date: string, contact_phone: string|null, direction: string, notes: string|null}>
     */
    public function fetchCalls(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $dateFromMs = (int) $dateFrom->getTimestamp() * 1000;
        $dateToMs = (int) $dateTo->getTimestamp() * 1000;
        $url = $this->baseUrl . '/api/v1/callHistory/enterprise';
        $all = [];
        $page = 0;
        $size = 100;

        do {
            $response = $this->requestCallHistory($url, $dateFromMs, $dateToMs, $page, $size);
            if ($response === null) {
                if ($page === 0) {
                    Log::warning('MtsVpbxService: запрос истории вызовов не удался', [
                        'url' => $url,
                        'date_from_ms' => $dateFromMs,
                        'date_to_ms' => $dateToMs,
                    ]);
                }
                break;
            }
            $content = $response['content'] ?? [];
            if (! is_array($content)) {
                break;
            }
            foreach ($content as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $item = $this->normalizeCallRow($row, null);
                if ($item !== null) {
                    $all[] = $item;
                }
            }
            $totalPages = (int) ($response['totalPages'] ?? 1);
            $page++;
        } while ($page < $totalPages);

        return $all;
    }

    /**
     * GET /api/v1/callHistory/enterprise с токеном X-AUTH-TOKEN (документация MTS API CRM).
     */
    private function requestCallHistory(string $url, int $dateFromMs, int $dateToMs, int $page, int $size): ?array
    {
        $params = [
            'dateFrom' => $dateFromMs,
            'dateTo' => $dateToMs,
            'page' => $page,
            'size' => $size,
        ];
        $opts = ['curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1']];
        $response = Http::timeout(30)
            ->withOptions($opts)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-AUTH-TOKEN' => $this->token,
            ])
            ->get($url, $params);

        if (! $response->successful()) {
            Log::debug('MtsVpbxService: ответ не успешен', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $body = $response->json();
        return is_array($body) ? $body : null;
    }

    /** Получить значение из массива по ключу без учёта регистра (ответ API может быть с разным casing). */
    private function arrayGet(array $row, string $key): mixed
    {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }
        $keyLower = strtolower($key);
        foreach ($row as $k => $v) {
            if (strtolower((string) $k) === $keyLower) {
                return $v;
            }
        }
        return null;
    }

    /**
     * Нормализация строки из MTS API (callTime в ms, callingNumber, calledNumber, direction ORIGINATING/TERMINATING, extTrackingId).
     */
    private function normalizeCallRow(array $row, ?string $fallbackId = null): ?array
    {
        $id = $this->arrayGet($row, 'extTrackingId') ?? $this->arrayGet($row, 'callGroupId') ?? $row['id'] ?? $row['call_id'] ?? $row['uuid'] ?? $row['record_id'] ?? $fallbackId;
        if ($id === null) {
            return null;
        }

        $start = $row['callTime'] ?? $row['start_time'] ?? $row['startTime'] ?? $row['date'] ?? $row['created_at'] ?? $row['call_start'] ?? $row['start'] ?? $row['datetime'] ?? $row['time'] ?? null;
        if ($start === null) {
            return null;
        }
        try {
            if (is_numeric($start)) {
                $ms = (int) $start;
                $dt = $ms > 1e12 ? \Carbon\Carbon::createFromTimestampMs($ms) : \Carbon\Carbon::createFromTimestamp($ms);
            } else {
                $dt = \Carbon\Carbon::parse($start);
            }
        } catch (\Throwable $e) {
            return null;
        }
        $contactDate = $dt->format('Y-m-d H:i:s');

        $caller = $row['callingNumber'] ?? $row['caller_number'] ?? $row['caller'] ?? $row['from'] ?? $row['from_number'] ?? $row['source'] ?? $row['caller_id'] ?? null;
        $called = $row['calledNumber'] ?? $row['called_number'] ?? $row['called'] ?? $row['to'] ?? $row['to_number'] ?? $row['destination'] ?? $row['callee'] ?? null;
        $dir = $row['direction'] ?? $row['type'] ?? $row['call_type'] ?? $row['call_direction'] ?? 'TERMINATING';
        $direction = (strtoupper((string) $dir) === 'ORIGINATING') ? 'outgoing' : 'incoming';

        $contactPhone = $direction === 'incoming' ? ($caller ?? $called) : ($called ?? $caller);
        if ($contactPhone !== null) {
            $contactPhone = (string) $contactPhone;
            $contactPhone = preg_replace('/\D/', '', $contactPhone);
            if (strlen($contactPhone) >= 10) {
                $contactPhone = '+' . (str_starts_with($contactPhone, '8') ? '7' . substr($contactPhone, 1) : (str_starts_with($contactPhone, '7') ? $contactPhone : '7' . $contactPhone));
            } else {
                $contactPhone = null;
            }
        }

        $duration = $row['duration'] ?? $row['talk_time'] ?? $row['billsec'] ?? $row['answer_sec'] ?? null;
        $status = $this->arrayGet($row, 'status') ?? '';
        $callStatus = (strtoupper((string) $status) === 'MISSED') ? 'missed' : 'placed';
        $notes = $duration !== null ? 'Длительность: ' . (int) $duration . ' сек.' : null;
        if ($callStatus === 'missed') {
            $notes = ($notes ? $notes . ' ' : '') . 'Пропущенный.';
        }

        $extTrackingId = $this->arrayGet($row, 'extTrackingId') ?? $this->arrayGet($row, 'ext_tracking_id');
        if ($extTrackingId !== null && $extTrackingId !== '') {
            $extTrackingId = (string) $extTrackingId;
        } else {
            $extTrackingId = null;
        }

        $durationSec = $duration !== null ? (int) $duration : null;

        return [
            'external_id' => 'mts_' . $id,
            'contact_date' => $contactDate,
            'contact_phone' => $contactPhone,
            'direction' => $direction,
            'notes' => $notes,
            'call_status' => $callStatus,
            'call_duration_sec' => $durationSec,
            'ext_tracking_id' => $extTrackingId ?: null,
        ];
    }

    /**
     * Скачать запись разговора (GET /api/callRecording/mp3/{extTrackingId}) и сохранить в storage.
     * Возвращает относительный путь к файлу или null при ошибке.
     */
    public function downloadRecording(string $extTrackingId): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $url = $this->baseUrl . '/api/callRecording/mp3/' . rawurlencode($extTrackingId);
        $opts = ['curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1']];
        $response = Http::timeout(60)
            ->withOptions($opts)
            ->withHeaders(['X-AUTH-TOKEN' => $this->token])
            ->get($url);

        if (! $response->successful()) {
            Log::debug('MtsVpbxService: запись не получена', ['ext_tracking_id' => $extTrackingId, 'status' => $response->status()]);
            return null;
        }

        $dir = 'call_recordings';
        $filename = preg_replace('/[^a-zA-Z0-9_\-.:]/', '_', $extTrackingId) . '.mp3';
        $path = $dir . '/' . $filename;
        $fullPath = storage_path('app/' . $path);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        if (file_put_contents($fullPath, $response->body()) === false) {
            return null;
        }

        return $path;
    }

    /**
     * Получить запись разговора с MTS без сохранения (для воспроизведения по ссылке).
     * Возвращает содержимое MP3 или null при ошибке.
     */
    public function fetchRecordingContent(string $extTrackingId): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $url = $this->baseUrl . '/api/callRecording/mp3/' . rawurlencode($extTrackingId);
        $opts = ['curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1']];
        $response = Http::timeout(60)
            ->withOptions($opts)
            ->withHeaders(['X-AUTH-TOKEN' => $this->token])
            ->get($url);

        if (! $response->successful()) {
            Log::debug('MtsVpbxService: запись не получена', ['ext_tracking_id' => $extTrackingId, 'status' => $response->status()]);
            return null;
        }

        return $response->body();
    }
}
