<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallCenterContact;
use App\Support\AgentTeamsToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Внутренний API для agent-teams-portal: звонки MTS из колл-центра lombard.home.
 */
class AgentTeamsMtsController extends Controller
{
    private static function contactDateIso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        try {
            return \Carbon\Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 100), 1), 500);
        $sinceId = (int) $request->query('since_id', 0);
        $incomingOnly = filter_var($request->query('incoming_only', true), FILTER_VALIDATE_BOOLEAN);

        $q = CallCenterContact::query()
            ->where('channel', 'phone')
            ->where('external_id', 'like', 'mts_%')
            ->orderBy('id');

        if ($sinceId > 0) {
            $q->where('id', '>', $sinceId);
        } elseif ($request->filled('since')) {
            try {
                $since = \Carbon\Carbon::parse((string) $request->query('since'));
                $q->where('contact_date', '>=', $since);
            } catch (\Throwable) {
                return response()->json(['message' => 'Invalid since parameter.'], 422);
            }
        }

        if ($incomingOnly) {
            $q->where('direction', 'incoming');
        }

        $rows = $q->limit($limit)->get();
        $maxId = $sinceId;
        $calls = [];
        foreach ($rows as $row) {
            $maxId = max($maxId, (int) $row->id);
            $calls[] = [
                'id' => (int) $row->id,
                'external_id' => (string) $row->external_id,
                'contact_date' => self::contactDateIso($row->contact_date),
                'contact_phone' => $row->contact_phone,
                'line_phone' => $row->line_phone,
                'direction' => $row->direction,
                'call_status' => $row->call_status,
                'call_duration_sec' => $row->call_duration_sec,
                'notes' => $row->notes,
                'recording_transcript' => $row->recording_transcript
                    ? mb_substr((string) $row->recording_transcript, 0, 12000)
                    : null,
                'has_recording' => $row->hasRecording(),
            ];
        }

        return response()->json([
            'calls' => $calls,
            'max_id' => $maxId,
            'count' => count($calls),
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'lombard-mts',
            'agent_teams_token_configured' => AgentTeamsToken::isConfigured(),
            'mts_configured' => app(\App\Services\MtsVpbxService::class)->isConfigured(),
        ]);
    }
}
