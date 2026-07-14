<?php

namespace App\Services;

use App\Models\CallCenterContact;
use Illuminate\Support\Facades\Log;

/** Пакетная автозагрузка записей, транскрипция и уведомление портала ИИ. */
class CallCenterMtsAutoEnrichmentService
{
    public function __construct(
        private CallCenterMtsRecordingService $recording,
        private AgentTeamsPortalNotifyService $portal,
    ) {}

    /**
     * @return array{processed: int, recordings: int, transcripts: int, portal_pushed: int, errors: int}
     */
    public function enrichContacts(iterable $contacts, ?int $limit = null): array
    {
        if (! (bool) config('services.mts_vpbx.auto_enrich_enabled', true)) {
            return $this->emptyStats();
        }

        $max = $limit ?? (int) config('services.mts_vpbx.auto_enrich_per_request', 100);
        $stats = $this->emptyStats();

        foreach ($contacts as $contact) {
            if (! $contact instanceof CallCenterContact) {
                continue;
            }
            if ($stats['processed'] >= $max) {
                break;
            }
            if (! $this->recording->isMtsPhoneCall($contact)) {
                continue;
            }
            $stats['processed']++;

            $hadTranscript = trim((string) $contact->recording_transcript) !== '';
            $row = $this->recording->enrich($contact);
            $contact->refresh();

            if ($row['recording_downloaded']) {
                $stats['recordings']++;
            }
            if ($row['transcript_updated']) {
                $stats['transcripts']++;
            }
            if ($row['error']) {
                $stats['errors']++;
            }

            if ($this->pushContactToPortalIfNeeded($contact, $hadTranscript, $row['transcript_updated'])) {
                $stats['portal_pushed']++;
            }
        }

        return $stats;
    }

    /**
     * Отправить в портал ИИ звонки с готовой расшифровкой (в т.ч. уже существующей в БД).
     *
     * @return array{processed: int, portal_pushed: int, errors: int}
     */
    public function pushContactsToPortal(iterable $contacts, ?int $limit = null): array
    {
        $max = $limit ?? (int) config('services.mts_vpbx.pipeline_portal_push_limit', 50);
        $stats = ['processed' => 0, 'portal_pushed' => 0, 'errors' => 0];

        if (! (bool) config('services.agent_teams.notify_portal_on_transcript', true)) {
            return $stats;
        }
        if (! $this->portal->isConfigured()) {
            return $stats;
        }

        foreach ($contacts as $contact) {
            if (! $contact instanceof CallCenterContact) {
                continue;
            }
            if ($stats['processed'] >= $max) {
                break;
            }
            if (! $this->recording->isMtsPhoneCall($contact)) {
                continue;
            }
            if (trim((string) $contact->recording_transcript) === '') {
                continue;
            }
            if ($contact->portal_pushed_at !== null) {
                continue;
            }

            $stats['processed']++;
            if ($this->doPushToPortal($contact)) {
                $stats['portal_pushed']++;
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * @return array{processed: int, recordings: int, transcripts: int, portal_pushed: int, errors: int}
     */
    private function emptyStats(): array
    {
        return [
            'processed' => 0,
            'recordings' => 0,
            'transcripts' => 0,
            'portal_pushed' => 0,
            'errors' => 0,
        ];
    }

    private function pushContactToPortalIfNeeded(
        CallCenterContact $contact,
        bool $hadTranscript,
        bool $transcriptUpdated
    ): bool {
        if (! (bool) config('services.agent_teams.notify_portal_on_transcript', true)) {
            return false;
        }
        if (! $this->portal->isConfigured()) {
            return false;
        }
        if (trim((string) $contact->recording_transcript) === '') {
            return false;
        }

        $needsPush = $transcriptUpdated
            || $contact->portal_pushed_at === null
            || (! $hadTranscript && trim((string) $contact->recording_transcript) !== '');

        if (! $needsPush && ! (bool) config('services.agent_teams.notify_portal_always_on_enrich', false)) {
            return false;
        }

        return $this->doPushToPortal($contact);
    }

    private function doPushToPortal(CallCenterContact $contact): bool
    {
        $res = $this->portal->pushMtsCall($contact);
        if ($res['ok']) {
            $contact->update(['portal_pushed_at' => now()]);

            return true;
        }

        Log::debug('AgentTeams portal push', ['contact_id' => $contact->id, 'msg' => $res['message']]);

        return false;
    }
}
