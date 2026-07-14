<?php

namespace App\Services;

use App\Models\CallCenterContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Загрузка записи MTS и расшифровка (МТС STT → Whisper) для звонка колл-центра.
 */
class CallCenterMtsRecordingService
{
    public function mtsTrackingId(CallCenterContact $contact): ?string
    {
        if (! empty($contact->ext_tracking_id)) {
            return (string) $contact->ext_tracking_id;
        }
        $external = (string) ($contact->external_id ?? '');
        if (str_starts_with($external, 'mts_ac20_')) {
            $rest = substr($external, strlen('mts_ac20_'));

            return $rest !== '' ? $rest : null;
        }
        if (str_starts_with($external, 'mts_')) {
            $rest = substr($external, 4);

            return $rest !== '' ? $rest : null;
        }

        return null;
    }

    public function isMtsPhoneCall(CallCenterContact $contact): bool
    {
        if ($contact->channel !== 'phone') {
            return false;
        }
        $external = (string) ($contact->external_id ?? '');

        return str_starts_with($external, 'mts_');
    }

    public function shouldFetchRecording(CallCenterContact $contact): bool
    {
        if (! $this->isMtsPhoneCall($contact)) {
            return false;
        }
        if ($contact->call_status === 'missed') {
            return false;
        }
        $dur = $contact->call_duration_sec;
        if ($dur !== null && (int) $dur <= 1) {
            return false;
        }
        $tracking = $this->mtsTrackingId($contact);

        return $tracking !== null && $tracking !== '' && preg_match('/^\d+$/', $tracking);
    }

    /**
     * @return array{recording_downloaded: bool, transcript_updated: bool, error: ?string}
     */
    public function enrich(CallCenterContact $contact): array
    {
        $result = [
            'recording_downloaded' => false,
            'transcript_updated' => false,
            'error' => null,
        ];
        if (! $this->isMtsPhoneCall($contact)) {
            return $result;
        }

        try {
            $result = $this->doEnrich($contact, $result);
        } catch (\Throwable $e) {
            Log::warning('CallCenterMtsRecording: enrich failed', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();
        }

        $this->recordEnrichAttempt($contact, $result);

        return $result;
    }

    /**
     * @param  array{recording_downloaded: bool, transcript_updated: bool, error: ?string}  $result
     * @return array{recording_downloaded: bool, transcript_updated: bool, error: ?string}
     */
    private function doEnrich(CallCenterContact $contact, array $result): array
    {
        $mts = app(MtsVpbxService::class);
        if (! $mts->isConfigured()) {
            $result['error'] = 'mts_not_configured';

            return $result;
        }

        $trackingId = $this->mtsTrackingId($contact);
        if ($this->shouldFetchRecording($contact) && empty($contact->recording_path) && $trackingId !== null) {
            $path = $mts->downloadRecording($trackingId);
            if ($path !== null) {
                $contact->recording_path = $path;
                $contact->save();
                $result['recording_downloaded'] = true;
                $contact->refresh();
            }
        }

        if (! empty(trim((string) $contact->recording_transcript))) {
            return $result;
        }

        if (! $this->shouldFetchRecording($contact) && empty($contact->recording_path)) {
            $result['error'] = $trackingId === null ? 'no_tracking' : 'skip_fetch';

            return $result;
        }

        $transcript = $this->resolveTranscript($contact, $mts, $trackingId);
        if ($transcript !== null && trim($transcript) !== '') {
            $contact->update([
                'recording_transcript' => $transcript,
                'portal_pushed_at' => null,
            ]);
            $result['transcript_updated'] = true;
        } else {
            $result['error'] = empty($contact->recording_path)
                ? 'recording_unavailable'
                : 'transcript_unavailable';
        }

        return $result;
    }

    /**
     * Учёт попыток: успешные сбрасываем, неудачные — backoff или «безнадёжный».
     *
     * @param  array{recording_downloaded: bool, transcript_updated: bool, error: ?string}  $result
     */
    private function recordEnrichAttempt(CallCenterContact $contact, array $result): void
    {
        $contact->refresh();

        if (! empty(trim((string) $contact->recording_transcript))) {
            if ((int) $contact->mts_enrich_attempts !== 0 || $contact->mts_enrich_next_at !== null) {
                $contact->update([
                    'mts_enrich_attempts' => 0,
                    'mts_enrich_next_at' => null,
                ]);
            }

            return;
        }

        // Ещё не было реальной попытки расшифровки (mts не настроен / не наш звонок).
        if ($result['error'] === 'mts_not_configured') {
            return;
        }

        $attempts = (int) $contact->mts_enrich_attempts + 1;
        $maxAttempts = max(1, (int) config('services.mts_vpbx.enrich_max_attempts', 3));
        $hopelessAfterDays = max(1, (int) config('services.mts_vpbx.enrich_hopeless_after_days', 14));
        $contactAt = $contact->contact_date;
        if (is_string($contactAt) && $contactAt !== '') {
            $contactAt = Carbon::parse($contactAt);
        }
        $ageDays = $contactAt instanceof Carbon
            ? (int) $contactAt->diffInDays(now())
            : 0;

        $hopeless = $attempts >= $maxAttempts
            || $result['error'] === 'no_tracking'
            || ($ageDays >= $hopelessAfterDays && empty($contact->recording_path) && $attempts >= 1);

        if ($hopeless) {
            $contact->update([
                'mts_enrich_attempts' => max($attempts, $maxAttempts),
                'mts_enrich_next_at' => now()->addYears(10),
            ]);
            Log::info('CallCenterMtsRecording: enrich marked hopeless', [
                'contact_id' => $contact->id,
                'attempts' => $attempts,
                'error' => $result['error'],
                'age_days' => $ageDays,
            ]);

            return;
        }

        $backoffMinutes = match ($attempts) {
            1 => 30,
            2 => 180,
            default => 720,
        };

        $contact->update([
            'mts_enrich_attempts' => $attempts,
            'mts_enrich_next_at' => now()->addMinutes($backoffMinutes),
        ]);
    }

    private function resolveTranscript(
        CallCenterContact $contact,
        MtsVpbxService $mts,
        ?string $trackingId
    ): ?string {
        if ($mts->usesAc20Api() && $trackingId !== null && preg_match('/^\d+$/', $trackingId)) {
            $fromMts = $mts->fetchCallSttTranscript($trackingId);
            if ($fromMts !== null && trim($fromMts) !== '') {
                return $fromMts;
            }
        }

        $audioPath = null;
        $tempPath = null;

        if (! empty($contact->recording_path) && Storage::disk('local')->exists($contact->recording_path)) {
            $audioPath = Storage::disk('local')->path($contact->recording_path);
        } elseif ($trackingId !== null && $trackingId !== '') {
            $content = $mts->fetchRecordingContent($trackingId);
            if ($content === null) {
                return null;
            }
            $tempPath = storage_path('app/temp_rec_'.$contact->id.'_'.time().'.mp3');
            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            if (file_put_contents($tempPath, $content) === false) {
                return null;
            }
            $audioPath = $tempPath;
        }

        if ($audioPath === null || ! is_readable($audioPath)) {
            return null;
        }

        try {
            return app(CallRecordingTranscriptionService::class)->transcribeAndFormat($audioPath);
        } catch (\Throwable $e) {
            Log::warning('CallCenterMtsRecording: transcribe failed', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
