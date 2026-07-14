<?php

namespace App\Services;

use App\Models\CallCenterContact;
use App\Support\AgentTeamsToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Push обновлённого звонка MTS в agent-teams-portal (inbox + маршрутизация). */
class AgentTeamsPortalNotifyService
{
    public function isConfigured(): bool
    {
        return AgentTeamsToken::isConfigured() && trim($this->portalBaseUrl()) !== '';
    }

    public function portalBaseUrl(): string
    {
        $url = rtrim(trim((string) config('services.agent_teams.portal_base_url', '')), '/');

        return $url !== '' ? $url : 'http://agent-teams.home';
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function pushMtsCall(CallCenterContact $contact): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'portal or token not configured'];
        }
        $external = (string) ($contact->external_id ?? '');
        if ($contact->channel !== 'phone' || ! str_starts_with($external, 'mts_')) {
            return ['ok' => false, 'message' => 'not an MTS call'];
        }

        $token = AgentTeamsToken::resolve();
        $url = $this->portalBaseUrl().'/api/internal/lombard/mts/call';

        $payload = [
            'id' => (int) $contact->id,
            'external_id' => $external,
            'contact_date' => $contact->contact_date,
            'contact_phone' => $contact->contact_phone,
            'line_phone' => $contact->line_phone,
            'direction' => $contact->direction,
            'call_status' => $contact->call_status,
            'call_duration_sec' => $contact->call_duration_sec,
            'notes' => $contact->notes,
            'recording_transcript' => $contact->recording_transcript
                ? mb_substr((string) $contact->recording_transcript, 0, 12000)
                : null,
            'has_recording' => $contact->hasRecording(),
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'X-Agent-Teams-Token' => $token,
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('AgentTeamsPortalNotify: request failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if (! $response->successful()) {
            Log::warning('AgentTeamsPortalNotify: HTTP error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
            ]);

            return ['ok' => false, 'message' => 'HTTP '.$response->status()];
        }

        return ['ok' => true, 'message' => 'pushed'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function pushTelegramMessage(\App\Models\TelegramMessage $message): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'portal or token not configured'];
        }

        $text = $message->displayText();
        if ($text === '') {
            return ['ok' => false, 'message' => 'empty message'];
        }

        $token = AgentTeamsToken::resolve();
        $url = $this->portalBaseUrl().'/api/internal/lombard/telegram/message';

        $payload = [
            'id' => (int) $message->id,
            'external_id' => $message->externalId(),
            'chat_id' => (string) $message->chat_id,
            'chat_type' => (string) $message->chat_type,
            'message_id' => (int) $message->message_id,
            'from_user_id' => $message->from_user_id,
            'from_username' => $message->from_username,
            'from_name' => $message->senderLabel(),
            'text' => mb_substr($text, 0, 12000),
            'message_type' => $message->message_type,
            'file_id' => $message->file_id,
            'file_name' => $message->file_name,
            'mime_type' => $message->mime_type,
            'client_id' => $message->client_id,
            'call_center_contact_id' => $message->call_center_contact_id,
            'message_date' => $message->message_date?->toIso8601String(),
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'X-Agent-Teams-Token' => $token,
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('AgentTeamsPortalNotify: telegram push failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if (! $response->successful()) {
            Log::warning('AgentTeamsPortalNotify: telegram HTTP error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
            ]);

            return ['ok' => false, 'message' => 'HTTP '.$response->status()];
        }

        return ['ok' => true, 'message' => 'pushed'];
    }
}
