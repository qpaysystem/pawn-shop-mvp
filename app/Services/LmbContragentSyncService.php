<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TrafficSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Синхронизация контрагентов из БД 1С (PostgreSQL) в таблицу clients.
 * Подтягивает: ФИО, телефон, паспорт, дату создания в 1С, источник рекламы.
 */
class LmbContragentSyncService
{
    private string $connection = 'lmb_1c_pgsql';

    private string $table;

    private ?string $phoneColumn;

    private ?string $nameColumn;

    private ?string $dateCreatedColumn;

    private ?string $trafficSourceRefColumn;

    private ?string $trafficSourceRefTable;

    private ?string $passportSeriesColumn;

    private ?string $passportNumberColumn;

    private ?string $inforegPassportTable;

    private ?string $inforegPeriodColumn;

    private ?string $inforegContragentRefColumn;

    private ?string $inforegSeriesColumn;

    private ?string $inforegNumberColumn;

    public function __construct()
    {
        $cfg = config('services.lmb_1c_contragent_sync', []);
        $this->table = $cfg['table'] ?? '_reference122x1';
        $this->phoneColumn = ! empty($cfg['phone_column']) ? $cfg['phone_column'] : null;
        $this->nameColumn = ! empty($cfg['name_column']) ? $cfg['name_column'] : null;
        $this->passportSeriesColumn = ! empty($cfg['passport_series_column']) ? $cfg['passport_series_column'] : null;
        $this->passportNumberColumn = ! empty($cfg['passport_number_column']) ? $cfg['passport_number_column'] : null;
        $this->dateCreatedColumn = ! empty($cfg['date_created_column']) ? $cfg['date_created_column'] : null;
        $this->trafficSourceRefColumn = ! empty($cfg['traffic_source_ref_column']) ? $cfg['traffic_source_ref_column'] : null;
        $this->trafficSourceRefTable = ! empty($cfg['traffic_source_ref_table']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['traffic_source_ref_table']) : null;
        $this->inforegPassportTable = ! empty($cfg['inforeg_passport_table']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['inforeg_passport_table']) : null;
        $this->inforegPeriodColumn = ! empty($cfg['inforeg_passport_period_column']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['inforeg_passport_period_column']) : null;
        $this->inforegContragentRefColumn = ! empty($cfg['inforeg_passport_contragent_ref_column']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['inforeg_passport_contragent_ref_column']) : null;
        $this->inforegSeriesColumn = ! empty($cfg['inforeg_passport_series_column']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['inforeg_passport_series_column']) : null;
        $this->inforegNumberColumn = ! empty($cfg['inforeg_passport_number_column']) ? preg_replace('/[^a-z0-9_]/i', '', $cfg['inforeg_passport_number_column']) : null;
    }

    /**
     * Синхронизировать контрагентов из 1С в clients.
     * Возвращает ['created' => int, 'updated' => int, 'skipped' => int, 'errors' => array, 'synced_uids' => array?].
     *
     * @param  callable|null  $progress  (int $processed, int $total) -> void
     * @param  bool  $onlyWithPassport  Если true, подтягивать только контрагентов с заполненными серией/номером паспорта в 1С.
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>, synced_uids?: array<int, string>}
     */
    public function sync(?callable $progress = null, bool $onlyWithPassport = false): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $syncedUids = [];

        $table = preg_replace('/[^a-z0-9_]/i', '', $this->table);
        $phoneCol = $this->phoneColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->phoneColumn) : null;
        $nameCol = $this->nameColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->nameColumn) : null;

        $selectCols = "encode(c._idrref, 'hex') as uid, c._description as desc_raw";
        $from = "public.{$table} c";
        if ($nameCol) {
            $selectCols .= ", c.\"{$nameCol}\" as name_raw";
        }
        if ($phoneCol) {
            $selectCols .= ", c.\"{$phoneCol}\" as phone_raw";
        }
        $passSeriesCol = $this->passportSeriesColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->passportSeriesColumn) : null;
        $passNumberCol = $this->passportNumberColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->passportNumberColumn) : null;
        if ($passSeriesCol && $passNumberCol) {
            $selectCols .= ", c.\"{$passSeriesCol}\" as passport_series, c.\"{$passNumberCol}\" as passport_number";
        } else {
            $selectCols .= ', NULL::text as passport_series, NULL::text as passport_number';
        }
        $dateCol = $this->dateCreatedColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->dateCreatedColumn) : null;
        if ($dateCol) {
            $selectCols .= ", c.\"{$dateCol}\" as date_created_raw";
        } else {
            $selectCols .= ', NULL::timestamp as date_created_raw';
        }
        $tsRefCol = $this->trafficSourceRefColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->trafficSourceRefColumn) : null;
        $tsRefTable = $this->trafficSourceRefTable;
        if ($tsRefCol && $tsRefTable) {
            $selectCols .= ', tr._description as traffic_source_name';
            $from .= " LEFT JOIN public.{$tsRefTable} tr ON tr._idrref = c.\"{$tsRefCol}\" AND NOT tr._marked";
        } else {
            $selectCols .= ', NULL::text as traffic_source_name';
        }

        $sql = "SELECT {$selectCols} FROM {$from} WHERE NOT c._marked";

        if ($onlyWithPassport && $passSeriesCol && $passNumberCol) {
            $sql .= " AND (TRIM(COALESCE(c.\"{$passSeriesCol}\"::text, '')) != '' OR TRIM(COALESCE(c.\"{$passNumberCol}\"::text, '')) != '')";
        }

        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            $errors[] = 'Ошибка чтения из 1С: '.$e->getMessage();

            return ['created' => 0, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
        }

        return $this->upsertClientsFromContragentRows(
            $rows,
            $table,
            $phoneCol,
            $nameCol,
            $passSeriesCol,
            $passNumberCol,
            false,
            $onlyWithPassport,
            $progress
        );
    }

    /**
     * Создать/обновить клиентов по списку UID контрагента из 1С (hex, как encode(_idrref)).
     * Используется перед синхронизацией скупки, чтобы подтянуть карточки покупателей.
     *
     * @param  list<string>  $uids
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function syncByUids(array $uids, ?callable $progress = null, bool $dryRun = false): array
    {
        $norm = [];
        foreach ($uids as $u) {
            $h = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $u));
            if (strlen($h) === 32) {
                $norm[$h] = true;
            }
        }
        $list = array_keys($norm);
        if ($list === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        }

        $table = preg_replace('/[^a-z0-9_]/i', '', $this->table);
        $phoneCol = $this->phoneColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->phoneColumn) : null;
        $nameCol = $this->nameColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->nameColumn) : null;
        $passSeriesCol = $this->passportSeriesColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->passportSeriesColumn) : null;
        $passNumberCol = $this->passportNumberColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->passportNumberColumn) : null;

        $selectCols = "encode(c._idrref, 'hex') as uid, c._description as desc_raw";
        $from = "public.{$table} c";
        if ($nameCol) {
            $selectCols .= ", c.\"{$nameCol}\" as name_raw";
        }
        if ($phoneCol) {
            $selectCols .= ", c.\"{$phoneCol}\" as phone_raw";
        }
        if ($passSeriesCol && $passNumberCol) {
            $selectCols .= ", c.\"{$passSeriesCol}\" as passport_series, c.\"{$passNumberCol}\" as passport_number";
        } else {
            $selectCols .= ', NULL::text as passport_series, NULL::text as passport_number';
        }
        $dateCol = $this->dateCreatedColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->dateCreatedColumn) : null;
        if ($dateCol) {
            $selectCols .= ", c.\"{$dateCol}\" as date_created_raw";
        } else {
            $selectCols .= ', NULL::timestamp as date_created_raw';
        }
        $tsRefCol = $this->trafficSourceRefColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->trafficSourceRefColumn) : null;
        $tsRefTable = $this->trafficSourceRefTable;
        if ($tsRefCol && $tsRefTable) {
            $selectCols .= ', tr._description as traffic_source_name';
            $from .= " LEFT JOIN public.{$tsRefTable} tr ON tr._idrref = c.\"{$tsRefCol}\" AND NOT tr._marked";
        } else {
            $selectCols .= ', NULL::text as traffic_source_name';
        }

        $errors = [];
        $allRows = [];
        foreach (array_chunk($list, 200) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT {$selectCols} FROM {$from} WHERE NOT c._marked AND encode(c._idrref, 'hex') IN ({$ph})";
            try {
                $part = DB::connection($this->connection)->select($sql, $chunk);
                foreach ($part as $r) {
                    $allRows[] = $r;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Ошибка чтения из 1С (по uid): '.$e->getMessage();
            }
        }

        if ($allRows === []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => $errors];
        }

        $result = $this->upsertClientsFromContragentRows(
            $allRows,
            $table,
            $phoneCol,
            $nameCol,
            $passSeriesCol,
            $passNumberCol,
            $dryRun,
            false,
            $progress
        );
        $result['errors'] = array_merge($errors, $result['errors']);

        return $result;
    }

    /**
     * @param  array<int, object>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>, synced_uids?: array<int, string>}
     */
    private function upsertClientsFromContragentRows(
        array $rows,
        string $table,
        ?string $phoneCol,
        ?string $nameCol,
        ?string $passSeriesCol,
        ?string $passNumberCol,
        bool $dryRun,
        bool $onlyWithPassport,
        ?callable $progress
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $syncedUids = [];

        $inforegPassportByUid = $this->loadInforegPassportMap();

        $identitySvc = app(Lmb1cIdentityDetailsService::class);
        $identityBlock = $identitySvc->preloadForContragentRows($rows);

        $total = count($rows);
        $processed = 0;

        foreach ($rows as $row) {
            $uid = $row->uid ?? null;
            $fullName = $nameCol && trim((string) ($row->name_raw ?? '')) !== ''
                ? $this->normalizeString($row->name_raw)
                : $this->normalizeString($row->desc_raw ?? '');
            $phoneRaw = $phoneCol ? $this->normalizeString($row->phone_raw ?? '') : '';

            $phone = $this->normalizePhone($phoneRaw);
            if ($phone === '' && $uid) {
                $phone = '1C-'.Str::limit($uid, 32);
            }
            if ($phone === '') {
                $skipped++;

                continue;
            }

            $phoneKey = $this->phoneKeyForMatching($phone);

            try {
                $existing = null;
                if ($uid) {
                    $existing = Client::where('user_uid', $uid)->first();
                }
                if (! $existing) {
                    $existing = Client::where('phone', $phone)->first();
                }
                if (! $existing && $phoneKey !== '') {
                    $existing = Client::where('phone_key', $phoneKey)->first();
                }

                $payload = [
                    'full_name' => $fullName ?: 'Без имени',
                    'lmb_full_name' => $fullName,
                    'user_uid' => $uid,
                ];
                if ($phoneKey !== '') {
                    $payload['phone_key'] = $phoneKey;
                }

                $lmbData = ['synced_from_1c_table' => $table];
                $passSeries = $this->normalizeString($row->passport_series ?? '');
                $passNumber = $this->normalizeString($row->passport_number ?? '');
                if ($passSeries === '' && $passNumber === '' && $uid && isset($inforegPassportByUid[$uid])) {
                    $passSeries = $inforegPassportByUid[$uid]['series'];
                    $passNumber = $inforegPassportByUid[$uid]['number'];
                    $lmbData['passport_from_inforeg'] = true;
                }
                if ($passSeries !== '' || $passNumber !== '') {
                    $lmbData['passport_series'] = $passSeries;
                    $lmbData['passport_number'] = $passNumber;
                }
                if (! empty($lmbData['passport_series']) || ! empty($lmbData['passport_number'])) {
                    $payload['passport_data'] = trim(($lmbData['passport_series'] ?? '').' '.($lmbData['passport_number'] ?? ''));
                }

                $fioKey = $identitySvc->fioKeyFromName($fullName);
                $extras = $identitySvc->mergeIntoPayload($identityBlock, (string) ($uid ?? ''), $fioKey);
                foreach ($extras as $field => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if ($field === 'lmb_passport_issued_at' && is_string($value)) {
                        try {
                            $payload[$field] = Carbon::parse($value)->format('Y-m-d');
                        } catch (\Throwable $e) {
                            // skip invalid date
                        }
                    } else {
                        $payload[$field] = $value;
                    }
                }
                if (isset($identityBlock['narratives'][$fioKey]['lmb_identity_raw_line'])) {
                    $lmbData['identity_document_line_from_1c'] = $identityBlock['narratives'][$fioKey]['lmb_identity_raw_line'];
                }

                $dateCreated = $this->parseDateCreated($row->date_created_raw ?? null);
                if ($dateCreated) {
                    $payload['lmb_created_at'] = $dateCreated;
                    $lmbData['lmb_created_at'] = $dateCreated->toIso8601String();
                }

                $trafficSourceName = trim((string) ($row->traffic_source_name ?? ''));
                if ($trafficSourceName !== '') {
                    $ts = TrafficSource::firstOrCreate(
                        ['name' => $trafficSourceName],
                        ['code' => Str::slug($trafficSourceName), 'sort_order' => 0]
                    );
                    $payload['traffic_source_id'] = $ts->id;
                    $lmbData['traffic_source_from_1c'] = $trafficSourceName;
                }

                if ($existing) {
                    if (! $dryRun) {
                        $existing->update($payload);
                        $existing->lmb_data = $lmbData;
                        $existing->save();
                    }
                    $updated++;
                } else {
                    if (! $dryRun) {
                        $payload['phone'] = $phone;
                        $payload['client_type'] = Client::TYPE_INDIVIDUAL;
                        $client = Client::create($payload);
                        $client->lmb_data = $lmbData;
                        $client->save();
                    }
                    $created++;
                }
                if ($onlyWithPassport && $uid !== null && $uid !== '') {
                    $syncedUids[] = $uid;
                }
            } catch (\Throwable $e) {
                $errors[] = "uid={$uid}: ".$e->getMessage();
            }

            $processed++;
            if ($progress && ($processed % 500 === 0 || $processed === $total)) {
                $progress($processed, $total);
            }
        }

        $result = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
        if ($onlyWithPassport) {
            $result['synced_uids'] = $syncedUids;
        }

        return $result;
    }

    /**
     * Подтянуть только паспортные данные из 1С для существующих клиентов
     * (только по фамилии; фамилия берётся как первое слово ФИО).
     * Не создаёт новых клиентов и не меняет остальные поля.
     *
     * Если в 1С по одной и той же фамилии есть несколько разных паспортов,
     * такие фамилии считаются неоднозначными и не используются для обновления.
     *
     * @param  bool  $dryRun  Если true, не записывать в БД, только посчитать обновления.
     * @return array{from_1c_with_passport: int, updated: int, not_found: int, skipped_no_identifier: int, errors: array<int, string>}
     */
    public function syncPassportOnly(?callable $progress = null, bool $dryRun = false): array
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $this->table);
        $phoneCol = $this->phoneColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->phoneColumn) : null;
        $passSeriesCol = $this->passportSeriesColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->passportSeriesColumn) : null;
        $passNumberCol = $this->passportNumberColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->passportNumberColumn) : null;
        if ($table !== '_reference122x1' || ! $phoneCol || ! $passSeriesCol || ! $passNumberCol) {
            return [
                'from_1c_with_passport' => 0,
                'updated' => 0,
                'not_found' => 0,
                'skipped_no_identifier' => 0,
                'errors' => ['Таблица должна быть _reference122x1 с phone_column и passport_series_column, passport_number_column.'],
            ];
        }

        $nameCol = $this->nameColumn ? preg_replace('/[^a-z0-9_]/i', '', $this->nameColumn) : null;
        $nameSelect = $nameCol ? ", c.\"{$nameCol}\" as name_raw" : ', NULL::text as name_raw';
        $sql = "SELECT encode(c._idrref, 'hex') as uid, c.\"{$phoneCol}\" as phone_raw{$nameSelect}, c.\"{$passSeriesCol}\" as passport_series, c.\"{$passNumberCol}\" as passport_number
                FROM public.{$table} c
                WHERE NOT c._marked
                  AND (TRIM(COALESCE(c.\"{$passSeriesCol}\"::text, '')) != '' OR TRIM(COALESCE(c.\"{$passNumberCol}\"::text, '')) != '')";
        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            return [
                'from_1c_with_passport' => 0,
                'updated' => 0,
                'not_found' => 0,
                'skipped_no_identifier' => 0,
                'errors' => ['Ошибка чтения из 1С: '.$e->getMessage()],
            ];
        }

        $passportsByLastName = [];
        foreach ($rows as $row) {
            $passport = trim(($this->normalizeString($row->passport_series ?? '').' '.$this->normalizeString($row->passport_number ?? '')));
            if ($passport === '') {
                continue;
            }
            $fioRaw = $this->normalizeString($row->name_raw ?? '');
            $lastName = $this->extractLastName($fioRaw);
            if ($lastName === '') {
                continue;
            }
            if (! isset($passportsByLastName[$lastName])) {
                $passportsByLastName[$lastName] = [];
            }
            // считаем набор разных паспортов по каждой фамилии
            $passportsByLastName[$lastName][$passport] = true;
        }

        // Оставляем только те фамилии, у которых ровно один уникальный паспорт
        $byLastName = [];
        foreach ($passportsByLastName as $lastName => $passportsSet) {
            if (count($passportsSet) === 1) {
                $passport = array_key_first($passportsSet);
                if ($passport !== null) {
                    $byLastName[$lastName] = $passport;
                }
            }
        }

        $from1cWithPassport = count($rows);
        $updated = 0;
        $notFound = 0;
        $skippedNoIdentifier = 0;
        $errors = [];

        $clients = Client::where('client_type', Client::TYPE_INDIVIDUAL)
            ->orderBy('id')
            ->get();

        $total = $clients->count();
        $processed = 0;

        foreach ($clients as $client) {
            $passport = null;

            // Фамилия клиента: сначала явное поле last_name, затем из full_name или lmb_full_name (первое слово)
            $clientLastName = $this->extractLastName((string) ($client->last_name ?? ''));
            if ($clientLastName === '') {
                $clientLastName = $this->extractLastName($this->normalizeString($client->full_name ?? ''));
            }
            if ($clientLastName === '') {
                $clientLastName = $this->extractLastName($this->normalizeString($client->lmb_full_name ?? ''));
            }

            if ($clientLastName === '') {
                $skippedNoIdentifier++;
                $processed++;
                if ($progress && ($processed % 1000 === 0 || $processed === $total)) {
                    $progress($processed, $total);
                }

                continue;
            }

            if (isset($byLastName[$clientLastName])) {
                $passport = $byLastName[$clientLastName];
            }

            if ($passport === null) {
                $notFound++;
                $processed++;
                if ($progress && ($processed % 1000 === 0 || $processed === $total)) {
                    $progress($processed, $total);
                }

                continue;
            }

            try {
                if (! $dryRun) {
                    $client->passport_data = $passport;
                    $lmb = $client->lmb_data ?? [];
                    if (! is_array($lmb)) {
                        $lmb = [];
                    }
                    $lmb['passport_synced_at'] = now()->toIso8601String();
                    $client->lmb_data = $lmb;
                    $client->save();
                }
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "client_id={$client->id}: ".$e->getMessage();
            }

            $processed++;
            if ($progress && ($processed % 1000 === 0 || $processed === $total)) {
                $progress($processed, $total);
            }
        }

        return [
            'from_1c_with_passport' => $from1cWithPassport,
            'updated' => $updated,
            'not_found' => $notFound,
            'skipped_no_identifier' => $skippedNoIdentifier,
            'fio_keys_count' => count($byLastName),
            'errors' => $errors,
        ];
    }

    /**
     * Обновить поле passport_data у уже импортированных клиентов (по user_uid) из регистра 1С.
     * По умолчанию не перезаписывает непустой passport_data (используйте $force).
     *
     * @return array{updated: int, skipped_existing_passport: int, not_in_inforeg: int, errors: array<int, string>}
     */
    public function syncPassportsFromInforegForExistingClients(bool $dryRun = false, bool $force = false): array
    {
        $map = $this->loadInforegPassportMap();
        $updated = 0;
        $skippedExisting = 0;
        $notInInforeg = 0;
        $errors = [];

        Client::query()
            ->where('client_type', Client::TYPE_INDIVIDUAL)
            ->whereNotNull('user_uid')
            ->orderBy('id')
            ->chunkById(500, function ($clients) use ($map, $dryRun, $force, &$updated, &$skippedExisting, &$notInInforeg, &$errors) {
                foreach ($clients as $client) {
                    $uid = trim((string) ($client->user_uid ?? ''));
                    if ($uid === '' || ! isset($map[$uid])) {
                        $notInInforeg++;

                        continue;
                    }
                    $ser = $map[$uid]['series'];
                    $num = $map[$uid]['number'];
                    $line = trim($ser.' '.$num);
                    if ($line === '') {
                        $notInInforeg++;

                        continue;
                    }
                    $current = trim((string) ($client->passport_data ?? ''));
                    if ($current !== '' && ! $force) {
                        $skippedExisting++;

                        continue;
                    }
                    try {
                        if (! $dryRun) {
                            $client->passport_data = mb_substr($line, 0, 500);
                            $lmb = $client->lmb_data ?? [];
                            if (! is_array($lmb)) {
                                $lmb = [];
                            }
                            $lmb['passport_series'] = $ser;
                            $lmb['passport_number'] = $num;
                            $lmb['passport_from_inforeg'] = true;
                            $lmb['passport_inforeg_synced_at'] = now()->toIso8601String();
                            $client->lmb_data = $lmb;
                            $client->save();
                        }
                        $updated++;
                    } catch (\Throwable $e) {
                        $errors[] = "client_id={$client->id}: ".$e->getMessage();
                    }
                }
            });

        return [
            'updated' => $updated,
            'skipped_existing_passport' => $skippedExisting,
            'not_in_inforeg' => $notInInforeg,
            'errors' => $errors,
        ];
    }

    /**
     * Дозаполнить вид документа, кем/когда выдан, адрес регистрации из 1С для уже импортированных клиентов.
     *
     * @return array{updated: int, errors: array<int, string>}
     */
    public function syncIdentityDetailsFrom1cForExistingClients(bool $dryRun = false): array
    {
        $updated = 0;
        $errors = [];
        $identitySvc = app(Lmb1cIdentityDetailsService::class);

        Client::query()
            ->where('client_type', Client::TYPE_INDIVIDUAL)
            ->whereNotNull('user_uid')
            ->orderBy('id')
            ->chunkById(300, function ($clients) use ($identitySvc, $dryRun, &$updated, &$errors) {
                $fakeRows = [];
                foreach ($clients as $c) {
                    $o = new \stdClass;
                    $o->uid = (string) ($c->user_uid ?? '');
                    $o->name_raw = $c->lmb_full_name ?: $c->full_name;
                    $o->desc_raw = '';
                    $fakeRows[] = $o;
                }
                $block = $identitySvc->preloadForContragentRows($fakeRows);
                foreach ($clients as $client) {
                    $uid = (string) ($client->user_uid ?? '');
                    $name = (string) ($client->lmb_full_name ?: $client->full_name);
                    $fk = $identitySvc->fioKeyFromName($name);
                    $extras = $identitySvc->mergeIntoPayload($block, $uid, $fk);
                    $raw = $block['narratives'][$fk]['lmb_identity_raw_line'] ?? null;
                    if ($extras === [] && ($raw === null || $raw === '')) {
                        continue;
                    }
                    try {
                        if (! $dryRun) {
                            foreach ($extras as $field => $value) {
                                if ($value === null || $value === '') {
                                    continue;
                                }
                                if ($field === 'lmb_passport_issued_at' && is_string($value)) {
                                    try {
                                        $client->{$field} = Carbon::parse($value)->format('Y-m-d');
                                    } catch (\Throwable $e) {
                                        // skip
                                    }
                                } else {
                                    $client->{$field} = $value;
                                }
                            }
                            if ($raw !== null && $raw !== '') {
                                $lmb = $client->lmb_data ?? [];
                                if (! is_array($lmb)) {
                                    $lmb = [];
                                }
                                $lmb['identity_document_line_from_1c'] = $raw;
                                $client->lmb_data = $lmb;
                            }
                            $client->save();
                        }
                        $updated++;
                    } catch (\Throwable $e) {
                        $errors[] = "client_id={$client->id}: ".$e->getMessage();
                    }
                }
            });

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * Последняя по периоду запись серии/номера паспорта из регистра сведений 1С по UID контрагента.
     *
     * @return array<string, array{series: string, number: string}>
     */
    private function loadInforegPassportMap(): array
    {
        if (! $this->inforegPassportTable || ! $this->inforegContragentRefColumn
            || ! $this->inforegSeriesColumn || ! $this->inforegNumberColumn) {
            return [];
        }

        $table = $this->inforegPassportTable;
        $cref = $this->inforegContragentRefColumn;
        $serCol = $this->inforegSeriesColumn;
        $numCol = $this->inforegNumberColumn;
        $periodCol = $this->inforegPeriodColumn ?: '_period';

        $sql = <<<SQL
SELECT DISTINCT ON (r."{$cref}")
  encode(r."{$cref}", 'hex') AS uid,
  trim(r."{$serCol}"::text) AS passport_series,
  trim(r."{$numCol}"::text) AS passport_number
FROM public.{$table} r
WHERE (
  trim(COALESCE(r."{$serCol}"::text, '')) != ''
  OR trim(COALESCE(r."{$numCol}"::text, '')) != ''
)
ORDER BY r."{$cref}", r."{$periodCol}" DESC NULLS LAST
SQL;

        try {
            $rows = DB::connection($this->connection)->select($sql);
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $uid = $row->uid ?? '';
            if ($uid === '') {
                continue;
            }
            $map[$uid] = [
                'series' => $this->normalizeString($row->passport_series ?? ''),
                'number' => $this->normalizeString($row->passport_number ?? ''),
            ];
        }

        return $map;
    }

    private function normalizeString(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        $s = trim($s);
        if (mb_strlen($s) > 255) {
            $s = mb_substr($s, 0, 255);
        }

        return $s;
    }

    private function normalizePhone(string $s): string
    {
        $digits = preg_replace('/\D/', '', $s);
        if (strlen($digits) >= 10) {
            if (str_starts_with($digits, '8') && strlen($digits) >= 11) {
                return '7'.substr($digits, 1, 10);
            }
            if (str_starts_with($digits, '7') && strlen($digits) >= 11) {
                return substr($digits, 0, 11);
            }

            return substr($digits, -10);
        }

        return $s ? $s : '';
    }

    /** Ключ для сопоставления по телефону: последние 7 цифр (чтобы 7/8 и формат не мешали). */
    private function phoneKeyForMatching(string $normalizedPhone): string
    {
        $digits = preg_replace('/\D/', '', $normalizedPhone);
        if (strlen($digits) >= 7) {
            return substr($digits, -7);
        }

        return '';
    }

    /** Нормализованный ключ ФИО для сопоставления (фамилия имя отчество в нижнем регистре, лишние пробелы убраны). */
    private function normalizeFio(string $fio): string
    {
        $fio = preg_replace('/\s+/u', ' ', trim($fio));
        if ($fio === '') {
            return '';
        }

        return mb_strtolower($fio, 'UTF-8');
    }

    /**
     * Извлечь фамилию из ФИО (первое слово, в нижнем регистре).
     */
    private function extractLastName(string $fio): string
    {
        $fioKey = $this->normalizeFio($fio);
        if ($fioKey === '') {
            return '';
        }
        $parts = explode(' ', $fioKey, 2);

        return $parts[0] ?? '';
    }

    private function parseDateCreated($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }
        try {
            $dt = Carbon::parse($value);
            if ($dt->year < 1990 || $dt->year > 2100) {
                return null;
            }

            return $dt;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
