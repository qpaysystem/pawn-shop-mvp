<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Телефония MTS: либо VPBX (vpbx.mts.ru, X-AUTH-TOKEN), либо Автосекретарь 2.0 (aa.mts.ru/api/ac20, Bearer JWT).
 *
 * @see https://aa.mts.ru/api/ac20/index.html
 */
class MtsVpbxService
{
    private string $baseUrl;

    private string $login;

    private string $token;

    private string $api;

    private string $ac20BaseUrl;

    private string $ac20Domain;

    private string $ac20TrunkId;

    /** Подсказка после последнего запроса AC20 statistics (ошибка HTTP / формат / парсинг строк). */
    private ?string $lastAc20StatisticsHint = null;

    public function __construct()
    {
        $cfg = config('services.mts_vpbx', []);
        $this->baseUrl = rtrim((string) ($cfg['url'] ?? 'https://vpbx.mts.ru'), '/');
        $this->login = (string) ($cfg['login'] ?? '');
        $this->token = (string) ($cfg['password'] ?? '');
        $this->api = strtolower((string) ($cfg['api'] ?? 'auto'));
        $this->ac20BaseUrl = rtrim((string) ($cfg['ac20_base_url'] ?? 'https://aa.mts.ru/api/ac20'), '/');
        $this->ac20Domain = trim((string) ($cfg['ac20_domain'] ?? ''));
        $this->ac20TrunkId = preg_replace('/\D/', '', (string) ($cfg['ac20_trunk_id'] ?? ''));
    }

    public function usesAc20Api(): bool
    {
        if ($this->api === 'ac20') {
            return true;
        }
        if ($this->api === 'vpbx') {
            return false;
        }
        // auto (по умолчанию) и прочие значения: AC20 только при полном наборе полей ЛК aa.mts.ru
        return $this->ac20Domain !== '' && strlen($this->ac20TrunkId) === 10;
    }

    /** Сообщение для UI/логов, если fetchCalls (AC20) вернул пустой список. */
    public function lastAc20StatisticsHint(): ?string
    {
        return $this->lastAc20StatisticsHint;
    }

    public function isConfigured(): bool
    {
        if ($this->token === '') {
            return false;
        }
        if ($this->usesAc20Api()) {
            return $this->ac20Domain !== '' && strlen($this->ac20TrunkId) === 10;
        }

        return true;
    }

    /** Заголовки авторизации: VPBX — X-AUTH-TOKEN; AC20 — Bearer JWT. */
    private function authHeaders(bool $jsonAccept = true): array
    {
        $h = [];
        if ($jsonAccept) {
            $h['Accept'] = 'application/json';
        }
        if ($this->usesAc20Api()) {
            $h['Authorization'] = 'Bearer '.$this->token;
        } else {
            $h['X-AUTH-TOKEN'] = $this->token;
        }

        return $h;
    }

    private function httpOptions(): array
    {
        return ['curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1']];
    }

    /**
     * AC20: GET /trunks/all — нужны только JWT и параметр Domain (из ЛК / Swagger).
     *
     * @return list<array<string, mixed>>|null
     */
    public function fetchAc20Trunks(string $domain): ?array
    {
        $domain = trim($domain);
        if (! $this->usesAc20Api() || $this->token === '' || $domain === '') {
            return null;
        }

        $url = $this->ac20BaseUrl.'/trunks/all';
        $response = Http::timeout(30)
            ->withOptions($this->httpOptions())
            ->withHeaders($this->authHeaders())
            ->get($url, ['Domain' => $domain]);

        if (! $response->successful()) {
            Log::debug('MtsVpbxService AC20: trunks/all failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
            ]);

            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Загрузить список звонков за период (API: GET /api/v1/callHistory/enterprise).
     * Возвращает массив: [ ['external_id' => ..., 'contact_date' => ..., 'contact_phone' => ..., 'direction' => ..., 'notes' => ..., 'call_status' => placed|missed, 'ext_tracking_id' => ...], ... ]
     *
     * @return array<int, array{external_id: string, contact_date: string, contact_phone: string|null, direction: string, notes: string|null}>
     */
    public function fetchCalls(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        if ($this->usesAc20Api()) {
            return $this->fetchCallsAc20($dateFrom, $dateTo);
        }

        $dateFromMs = (int) $dateFrom->getTimestamp() * 1000;
        $dateToMs = (int) $dateTo->getTimestamp() * 1000;
        $url = $this->baseUrl.'/api/v1/callHistory/enterprise';
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
        $response = Http::timeout(30)
            ->withOptions($this->httpOptions())
            ->withHeaders($this->authHeaders())
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

    /**
     * Автосекретарь 2.0: GET /trunks/statistics (пагинация Limit до 1000, Offset).
     *
     * @return list<array<string, mixed>>
     */
    private function fetchCallsAc20(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTo): array
    {
        $this->lastAc20StatisticsHint = null;

        $url = $this->ac20BaseUrl.'/trunks/statistics';
        $begin = $dateFrom->format('Y-m-d\TH:i:s');
        $end = $dateTo->format('Y-m-d\TH:i:s');
        $limit = 1000;
        $offset = 0;
        $all = [];

        do {
            $params = [
                'Domain' => $this->ac20Domain,
                'TrunkId' => $this->ac20TrunkId,
                'Begin' => $begin,
                'End' => $end,
                'Limit' => $limit,
                'Offset' => $offset,
            ];
            $response = Http::timeout(60)
                ->withOptions($this->httpOptions())
                ->withHeaders($this->authHeaders())
                ->get($url, $params);

            if (! $response->successful()) {
                $snippet = substr($response->body(), 0, 400);
                Log::warning('MtsVpbxService AC20: statistics request failed', [
                    'status' => $response->status(),
                    'body' => $snippet,
                ]);
                $this->lastAc20StatisticsHint = 'Запрос /trunks/statistics: HTTP '.$response->status().'. '.($snippet !== '' ? 'Ответ: '.$snippet : 'Пустой ответ.');

                break;
            }
            $decoded = $response->json();
            $rows = $this->unwrapAc20StatisticsRows($decoded);
            if ($rows === null) {
                $raw = substr($response->body(), 0, 500);
                Log::warning('MtsVpbxService AC20: statistics JSON не массив вызовов', ['sample' => $raw]);
                $this->lastAc20StatisticsHint = 'Ответ API не похож на массив звонков. Начало тела: '.$raw;

                break;
            }
            $batch = 0;
            $parsedInPage = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $item = $this->normalizeAc20StatisticsRow($row);
                if ($item !== null) {
                    $all[] = $item;
                    $parsedInPage++;
                }
                $batch++;
            }
            if ($offset === 0 && $batch > 0 && $parsedInPage === 0) {
                $this->lastAc20StatisticsHint = 'API вернул '.$batch.' запис(ей), но ни одна не распознана (нужны callId и время начала). См. php artisan mts:debug-response --days=7';
            }
            if ($batch < $limit) {
                break;
            }
            $offset += $limit;
        } while (true);

        return $all;
    }

    /**
     * Развернуть тело ответа /trunks/statistics в список объектов CallStatistics.
     *
     * @return list<array<string, mixed>>|null null — не удалось интерпретировать
     */
    private function unwrapAc20StatisticsRows(mixed $decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }
        if ($decoded === []) {
            return [];
        }
        if (array_is_list($decoded)) {
            return $decoded;
        }
        foreach (['data', 'items', 'calls', 'statistics', 'result', 'value', 'content'] as $key) {
            if (! isset($decoded[$key]) || ! is_array($decoded[$key])) {
                continue;
            }
            $inner = $decoded[$key];
            if (array_is_list($inner)) {
                return $inner;
            }
            if (! array_is_list($inner) && $this->arrayGet($inner, 'callId') !== null) {
                return [$inner];
            }
        }
        if ($this->arrayGet($decoded, 'callId') !== null) {
            return [$decoded];
        }

        return null;
    }

    /**
     * Нормализация строки CallStatistics (AC20).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeAc20StatisticsRow(array $row): ?array
    {
        $callId = $this->arrayGet($row, 'callId')
            ?? $this->arrayGet($row, 'CallId')
            ?? $this->arrayGet($row, 'id')
            ?? $this->arrayGet($row, 'call_id');
        if ($callId === null || $callId === '') {
            return null;
        }
        $callIdStr = (string) $callId;

        $start = $this->arrayGet($row, 'startTime')
            ?? $this->arrayGet($row, 'StartTime')
            ?? $this->arrayGet($row, 'beginTime')
            ?? $this->arrayGet($row, 'start')
            ?? $this->arrayGet($row, 'date');
        if ($start === null || $start === '') {
            return null;
        }
        try {
            $dt = \Carbon\Carbon::parse($start);
        } catch (\Throwable $e) {
            return null;
        }
        $contactDate = $dt->format('Y-m-d H:i:s');

        $an = $this->arrayGet($row, 'an');
        $dn = $this->arrayGet($row, 'dn');
        $anDigits = $an !== null && $an !== '' ? preg_replace('/\D/', '', (string) $an) : '';
        $dnDigits = $dn !== null && $dn !== '' ? preg_replace('/\D/', '', (string) $dn) : '';

        $rel = $this->arrayGet($row, 'rel');
        $isInternal = (int) $rel === 1;

        // Внешний звонок: клиент с длинным номером на коротком/служебном — входящий (звонят нам) или исходящий (мы звоним клиенту).
        $direction = 'incoming';
        $rawPhone = $an;
        if (! $isInternal) {
            if (strlen($anDigits) >= 10 && strlen($dnDigits) < 10) {
                $direction = 'incoming';
                $rawPhone = $an;
            } elseif (strlen($dnDigits) >= 10 && strlen($anDigits) < 10) {
                $direction = 'outgoing';
                $rawPhone = $dn;
            } else {
                $rawPhone = $an ?: $dn;
            }
        } else {
            $rawPhone = $an ?: $dn;
        }

        $contactPhone = $this->normalizePhoneDigits($rawPhone);

        $duration = $this->arrayGet($row, 'duration');
        $durationSec = is_numeric($duration) ? (int) $duration : null;
        $callStatus = ($durationSec !== null && $durationSec > 0) ? 'placed' : 'missed';
        $notes = $durationSec !== null ? 'Длительность: '.$durationSec.' сек. (AC20)' : null;
        if ($callStatus === 'missed' && $notes !== null) {
            $notes .= ' Пропущенный / без ответа.';
        } elseif ($callStatus === 'missed') {
            $notes = 'Пропущенный / без ответа (AC20).';
        }

        $recDur = $this->arrayGet($row, 'recDur');
        $hasRecording = is_numeric($recDur) && (int) $recDur > 0;

        return [
            'external_id' => 'mts_ac20_'.$callIdStr,
            'contact_date' => $contactDate,
            'contact_phone' => $contactPhone,
            'direction' => $direction,
            'notes' => $notes,
            'call_status' => $callStatus,
            'call_duration_sec' => $durationSec,
            'ext_tracking_id' => $hasRecording ? $callIdStr : null,
        ];
    }

    private function normalizePhoneDigits(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $contactPhone = preg_replace('/\D/', '', (string) $raw);
        if (strlen($contactPhone) < 10) {
            return null;
        }
        $contactPhone = '+'.(str_starts_with($contactPhone, '8') ? '7'.substr($contactPhone, 1) : (str_starts_with($contactPhone, '7') ? $contactPhone : '7'.$contactPhone));

        return $contactPhone;
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
                $contactPhone = '+'.(str_starts_with($contactPhone, '8') ? '7'.substr($contactPhone, 1) : (str_starts_with($contactPhone, '7') ? $contactPhone : '7'.$contactPhone));
            } else {
                $contactPhone = null;
            }
        }

        $duration = $row['duration'] ?? $row['talk_time'] ?? $row['billsec'] ?? $row['answer_sec'] ?? null;
        $status = $this->arrayGet($row, 'status') ?? '';
        $callStatus = (strtoupper((string) $status) === 'MISSED') ? 'missed' : 'placed';
        $notes = $duration !== null ? 'Длительность: '.(int) $duration.' сек.' : null;
        if ($callStatus === 'missed') {
            $notes = ($notes ? $notes.' ' : '').'Пропущенный.';
        }

        $extTrackingId = $this->arrayGet($row, 'extTrackingId') ?? $this->arrayGet($row, 'ext_tracking_id');
        if ($extTrackingId !== null && $extTrackingId !== '') {
            $extTrackingId = (string) $extTrackingId;
        } else {
            $extTrackingId = null;
        }

        $durationSec = $duration !== null ? (int) $duration : null;

        return [
            'external_id' => 'mts_'.$id,
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
        if ($this->usesAc20Api()) {
            $binary = $this->fetchRecordingBinaryAc20($extTrackingId);
            if ($binary === null) {
                return null;
            }
            $dir = 'call_recordings';
            $filename = preg_replace('/[^a-zA-Z0-9_\-.:]/', '_', $extTrackingId).'.mp3';
            $path = $dir.'/'.$filename;
            $fullPath = storage_path('app/'.$path);
            if (! is_dir(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }
            if (file_put_contents($fullPath, $binary) === false) {
                return null;
            }

            return $path;
        }

        $url = $this->baseUrl.'/api/callRecording/mp3/'.rawurlencode($extTrackingId);
        $response = Http::timeout(60)
            ->withOptions($this->httpOptions())
            ->withHeaders($this->authHeaders(false))
            ->get($url);

        if (! $response->successful()) {
            Log::debug('MtsVpbxService: запись не получена', ['ext_tracking_id' => $extTrackingId, 'status' => $response->status()]);

            return null;
        }

        $dir = 'call_recordings';
        $filename = preg_replace('/[^a-zA-Z0-9_\-.:]/', '_', $extTrackingId).'.mp3';
        $path = $dir.'/'.$filename;
        $fullPath = storage_path('app/'.$path);
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
        if ($this->usesAc20Api()) {
            return $this->fetchRecordingBinaryAc20($extTrackingId);
        }

        $url = $this->baseUrl.'/api/callRecording/mp3/'.rawurlencode($extTrackingId);
        $response = Http::timeout(60)
            ->withOptions($this->httpOptions())
            ->withHeaders($this->authHeaders(false))
            ->get($url);

        if (! $response->successful()) {
            Log::debug('MtsVpbxService: запись не получена', ['ext_tracking_id' => $extTrackingId, 'status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * AC20: GET /stt/result — готовая расшифровка от МТС (фразы с привязкой к стороне звонка).
     * Нужна услуга «Сервисы ИИ» / распознавание на транке. 204 — результат ещё не готов.
     *
     * @see https://aa.mts.ru/api/ac20/index.html
     */
    public function fetchCallSttTranscript(string $callIdStr): ?string
    {
        if (! $this->usesAc20Api() || ! $this->isConfigured()) {
            return null;
        }
        if (! preg_match('/^\d+$/', $callIdStr)) {
            return null;
        }
        $url = $this->ac20BaseUrl.'/stt/result';
        $params = [
            'Domain' => $this->ac20Domain,
            'TrunkId' => $this->ac20TrunkId,
            'CallId' => (int) $callIdStr,
        ];
        $response = Http::timeout(60)
            ->withOptions($this->httpOptions())
            ->withHeaders($this->authHeaders())
            ->get($url, $params);

        if ($response->status() === 204) {
            return null;
        }
        if (! $response->successful()) {
            Log::debug('MtsVpbxService AC20: stt/result failed', [
                'call_id' => $callIdStr,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
            ]);

            return null;
        }
        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }
        $status = $data['status'] ?? '';
        if ((string) $status !== 'Completed') {
            return null;
        }
        $phrases = $data['phrases'] ?? null;
        if (! is_array($phrases) || $phrases === []) {
            return null;
        }

        return $this->formatAc20SttPhrases($phrases);
    }

    /**
     * @param  list<array<string, mixed>>  $phrases
     */
    private function formatAc20SttPhrases(array $phrases): ?string
    {
        $lines = [];
        foreach ($phrases as $p) {
            if (! is_array($p)) {
                continue;
            }
            $text = trim((string) ($p['phrase'] ?? ''));
            if ($text === '') {
                continue;
            }
            $sideRaw = $p['callSide'] ?? $p['callside'] ?? '';
            $side = is_string($sideRaw) ? strtolower($sideRaw) : '';
            $label = match ($side) {
                'user' => 'Клиент',
                'operator' => 'Оператор',
                'bot' => 'Бот',
                default => '',
            };
            $lines[] = $label !== '' ? "{$label}: {$text}" : $text;
        }

        $out = trim(implode("\n", $lines));

        return $out !== '' ? $out : null;
    }

    private function fetchRecordingBinaryAc20(string $callIdStr): ?string
    {
        if (! preg_match('/^\d+$/', $callIdStr)) {
            return null;
        }
        $url = $this->ac20BaseUrl.'/trunks/record';
        $params = [
            'Domain' => $this->ac20Domain,
            'TrunkId' => $this->ac20TrunkId,
            'CallId' => (int) $callIdStr,
            'Type' => 'mp3',
        ];
        $response = Http::timeout(120)
            ->withOptions($this->httpOptions())
            ->withHeaders($this->authHeaders(false))
            ->get($url, $params);

        if (! $response->successful()) {
            Log::debug('MtsVpbxService AC20: запись не получена', ['call_id' => $callIdStr, 'status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * Для mts:debug-response: сколько записей после unwrap и сколько распарсилось в CRM.
     *
     * @return array{rows: int|null, parsed: int, unwrap_ok: bool}
     */
    public function summarizeAc20StatisticsPayload(mixed $decoded): array
    {
        $rows = $this->unwrapAc20StatisticsRows($decoded);
        if ($rows === null) {
            return ['rows' => null, 'parsed' => 0, 'unwrap_ok' => false];
        }
        $parsed = 0;
        foreach ($rows as $row) {
            if (is_array($row) && $this->normalizeAc20StatisticsRow($row) !== null) {
                $parsed++;
            }
        }

        return ['rows' => count($rows), 'parsed' => $parsed, 'unwrap_ok' => true];
    }
}
