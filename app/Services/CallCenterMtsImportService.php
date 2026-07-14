<?php

namespace App\Services;

use App\Models\CallCenterContact;
use App\Models\Client;
use Illuminate\Support\Collection;

/** Импорт звонков MTS в call_center_contacts (как кнопка в колл-центре). */
class CallCenterMtsImportService
{
    /**
     * @return array{created: int, updated: int, contact_ids: list<int>, fetched: int}
     */
    public function importRecentCalls(int $days = 1): array
    {
        $service = app(MtsVpbxService::class);
        if (! $service->isConfigured()) {
            return ['created' => 0, 'updated' => 0, 'contact_ids' => [], 'fetched' => 0];
        }

        $days = max(1, min(90, $days));
        $dateFrom = now()->subDays($days)->startOfDay();
        $dateTo = now();
        $calls = $service->fetchCalls($dateFrom, $dateTo);

        $created = 0;
        $updated = 0;
        $ids = [];

        foreach ($calls as $call) {
            $existing = CallCenterContact::where('external_id', $call['external_id'])->first();

            if ($existing) {
                $patch = [];
                if (isset($call['call_status'])) {
                    $patch['call_status'] = $call['call_status'];
                }
                if (array_key_exists('call_duration_sec', $call)) {
                    $patch['call_duration_sec'] = $call['call_duration_sec'];
                }
                if (array_key_exists('ext_tracking_id', $call) && $call['ext_tracking_id'] !== null) {
                    $patch['ext_tracking_id'] = $call['ext_tracking_id'];
                }
                if (! empty($call['line_phone'])) {
                    $patch['line_phone'] = $call['line_phone'];
                }
                if (! empty($call['direction'])) {
                    $patch['direction'] = $call['direction'];
                }
                if ($patch !== []) {
                    $existing->update($patch);
                    $updated++;
                }
                $ids[] = (int) $existing->id;

                continue;
            }

            $clientId = null;
            if (! empty($call['contact_phone'])) {
                $phone = $call['contact_phone'];
                $normalized = preg_replace('/\D/', '', $phone);
                $client = Client::where('phone', $phone)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?", [$normalized])
                    ->first();
                $clientId = $client?->id;
            }

            $row = CallCenterContact::create([
                'external_id' => $call['external_id'],
                'ext_tracking_id' => $call['ext_tracking_id'] ?? null,
                'client_id' => $clientId,
                'channel' => 'phone',
                'direction' => $call['direction'],
                'call_status' => $call['call_status'] ?? null,
                'call_duration_sec' => $call['call_duration_sec'] ?? null,
                'store_id' => null,
                'contact_date' => $call['contact_date'],
                'contact_phone' => $call['contact_phone'],
                'line_phone' => $call['line_phone'] ?? null,
                'contact_name' => null,
                'notes' => $call['notes'],
                'outcome' => null,
                'created_by' => null,
            ]);
            $created++;
            $ids[] = (int) $row->id;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'contact_ids' => array_values(array_unique($ids)),
            'fetched' => count($calls),
        ];
    }

    /**
     * Звонки MTS без расшифровки — свежие первыми; безнадёжные/отложенные не берём.
     */
    public function contactsPendingEnrichment(int $days, int $limit): Collection
    {
        $from = now()->subDays(max(1, $days))->startOfDay();
        $maxAttempts = max(1, (int) config('services.mts_vpbx.enrich_max_attempts', 3));

        return CallCenterContact::query()
            ->where('channel', 'phone')
            ->where('external_id', 'like', 'mts_%')
            ->where('contact_date', '>=', $from)
            ->where(function ($q) {
                $q->whereNull('recording_transcript')
                    ->orWhere('recording_transcript', '');
            })
            ->where(function ($q) {
                $q->where('call_status', '!=', 'missed')
                    ->orWhereNull('call_status');
            })
            ->where(function ($q) {
                $q->whereNull('call_duration_sec')
                    ->orWhere('call_duration_sec', '>', 1);
            })
            ->where('mts_enrich_attempts', '<', $maxAttempts)
            ->where(function ($q) {
                $q->whereNull('mts_enrich_next_at')
                    ->orWhere('mts_enrich_next_at', '<=', now());
            })
            ->orderByDesc('contact_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Звонки с расшифровкой, ещё не отправленные в портал ИИ.
     */
    public function contactsPendingPortalPush(int $days, int $limit): Collection
    {
        $from = now()->subDays(max(1, $days))->startOfDay();

        return CallCenterContact::query()
            ->where('channel', 'phone')
            ->where('external_id', 'like', 'mts_%')
            ->where('contact_date', '>=', $from)
            ->whereNotNull('recording_transcript')
            ->where('recording_transcript', '!=', '')
            ->whereNull('portal_pushed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
