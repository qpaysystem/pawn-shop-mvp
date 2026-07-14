<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramMessage;
use App\Services\LombardSnapshotService;
use App\Services\TelegramService;
use App\Support\AgentTeamsToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Внутренний API для agent-teams-portal: Telegram inbox lombard.home.
 */
class AgentTeamsTelegramController extends Controller
{
    public function health(): JsonResponse
    {
        $chatIds = TelegramService::configuredInboxChatIds();
        $last = TelegramMessage::query()->orderByDesc('id')->first();

        return response()->json([
            'ok' => true,
            'service' => 'lombard-telegram',
            'agent_teams_token_configured' => AgentTeamsToken::isConfigured(),
            'inbox_chat_ids' => $chatIds,
            'private_inbox_enabled' => TelegramService::privateInboxEnabled(),
            'messages_total' => TelegramMessage::query()->count(),
            'last_message_id' => $last?->id,
            'last_message_at' => $last?->message_date?->toIso8601String(),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $sinceId = max(0, (int) $request->query('since_id', 0));
        $limit = min(100, max(1, (int) $request->query('limit', 100)));
        $chatId = trim((string) $request->query('chat_id', ''));
        if ($chatId !== '') {
            $chatId = TelegramService::normalizeChatIdForStorage($chatId);
        }

        $q = TelegramMessage::query()->where('id', '>', $sinceId)->orderBy('id');
        if ($chatId !== '') {
            $q->where('chat_id', $chatId);
        }

        $rows = $q->limit($limit)->get();
        $messages = [];
        $maxId = $sinceId;

        foreach ($rows as $row) {
            $maxId = max($maxId, (int) $row->id);
            $text = $row->displayText();
            if ($text === '') {
                continue;
            }
            if ($row->from_user_id === null && str_contains((string) $row->from_first_name, 'ИИ-агент')) {
                continue;
            }

            $messages[] = [
                'id' => (int) $row->id,
                'external_id' => $row->externalId(),
                'chat_id' => (string) $row->chat_id,
                'chat_type' => (string) $row->chat_type,
                'message_id' => (int) $row->message_id,
                'from_user_id' => $row->from_user_id,
                'from_username' => $row->from_username,
                'from_name' => $row->senderLabel(),
                'text' => $text,
                'message_type' => $row->message_type,
                'caption' => $row->caption,
                'file_id' => $row->file_id,
                'file_name' => $row->file_name,
                'mime_type' => $row->mime_type,
                'file_size' => $row->file_size,
                'client_id' => $row->client_id,
                'call_center_contact_id' => $row->call_center_contact_id,
                'message_date' => $row->message_date instanceof \DateTimeInterface
                    ? $row->message_date->format(\DateTimeInterface::ATOM)
                    : ($row->message_date ? (string) $row->message_date : null),
            ];
        }

        return response()->json([
            'messages' => $messages,
            'max_id' => $maxId,
        ]);
    }

    public function reply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_id' => 'required|string|max:64',
            'text' => 'required|string|max:3900',
            'reply_to_message_id' => 'nullable|integer|min:1',
        ]);

        $token = trim((string) \App\Models\Setting::get('telegram_bot_token', ''));
        if ($token === '') {
            return response()->json(['ok' => false, 'error' => 'telegram_bot_token not configured'], 503);
        }

        $chatId = TelegramService::normalizeChatIdForStorage((string) $data['chat_id']);
        $replyTo = isset($data['reply_to_message_id']) ? (int) $data['reply_to_message_id'] : null;
        $result = TelegramService::sendPlainMessage(
            $token,
            $chatId,
            (string) $data['text'],
            true,
            'Ломбард (ИИ)',
            $replyTo,
        );

        return response()->json(['ok' => (bool) ($result['ok'] ?? false), 'error' => $result['error'] ?? null]);
    }

    public function lombardSnapshot(): JsonResponse
    {
        return response()->json(app(LombardSnapshotService::class)->build());
    }
}
