<?php

namespace App\Services;

use App\Models\CallCenterContact;
use App\Models\Client;
use App\Models\TelegramMessage;
use Illuminate\Support\Carbon;

/** Сохранение Telegram-сообщения в inbox ломбарда и колл-центр. */
class TelegramInboxService
{
    /**
     * @param  array<string, mixed>  $message  Объект message из Telegram Update.
     */
    public function ingestFromTelegramMessage(array $message): ?TelegramMessage
    {
        $chat = $message['chat'] ?? null;
        if (! is_array($chat)) {
            return null;
        }

        $chatId = TelegramService::normalizeChatIdForStorage((string) ($chat['id'] ?? ''));
        $messageId = (int) ($message['message_id'] ?? 0);
        if ($chatId === '' || $messageId <= 0) {
            return null;
        }

        $from = is_array($message['from'] ?? null) ? $message['from'] : null;
        $isBot = is_array($from) && ! empty($from['is_bot']);
        $chatType = (string) ($chat['type'] ?? 'private');

        [$messageType, $fileId, $fileUniqueId, $fileName, $mimeType, $fileSize] = $this->extractAttachment($message);

        $text = isset($message['text']) ? (string) $message['text'] : null;
        $caption = isset($message['caption']) ? (string) $message['caption'] : null;
        $date = isset($message['date']) ? (int) $message['date'] : null;

        $row = TelegramMessage::query()->firstOrCreate(
            [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ],
            [
                'chat_type' => $chatType,
                'message_type' => $messageType,
                'from_user_id' => $isBot ? null : (isset($from['id']) ? (int) $from['id'] : null),
                'from_username' => is_array($from) && isset($from['username']) ? (string) $from['username'] : null,
                'from_first_name' => $this->fromDisplayName($from, $isBot),
                'text' => $text,
                'caption' => $caption,
                'file_id' => $fileId,
                'file_unique_id' => $fileUniqueId,
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'message_date' => $date ? Carbon::createFromTimestamp($date) : now(),
            ]
        );

        if (! $isBot && $row->call_center_contact_id === null) {
            $client = $this->resolveClient($from);
            if ($client) {
                $row->client_id = $client->id;
                $row->save();
            }
            $contact = $this->createCallCenterContact($row, $client);
            $row->call_center_contact_id = $contact->id;
            $row->save();
        }

        if ((bool) config('services.agent_teams.notify_portal_on_telegram', true)) {
            $push = app(AgentTeamsPortalNotifyService::class)->pushTelegramMessage($row);
            if ($push['ok']) {
                $row->update(['portal_pushed_at' => now()]);
            }
        }

        return $row->fresh(['client', 'callCenterContact']);
    }

    /**
     * @param  array<string, mixed>|null  $from
     */
    private function fromDisplayName(?array $from, bool $isBot): ?string
    {
        if ($isBot) {
            return 'ИИ-агент';
        }
        if (! is_array($from)) {
            return null;
        }
        $name = trim(((string) ($from['first_name'] ?? '')).' '.((string) ($from['last_name'] ?? '')));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>|null  $from
     */
    private function resolveClient(?array $from): ?Client
    {
        if (! is_array($from)) {
            return null;
        }
        $userId = isset($from['id']) ? (int) $from['id'] : null;
        if ($userId) {
            $byId = Client::query()->where('telegram_id', $userId)->first();
            if ($byId) {
                return $byId;
            }
        }
        $username = isset($from['username']) ? ltrim((string) $from['username'], '@') : '';
        if ($username !== '') {
            return Client::query()
                ->where('telegram_username', $username)
                ->orWhere('telegram_username', '@'.$username)
                ->first();
        }

        return null;
    }

    private function createCallCenterContact(TelegramMessage $row, ?Client $client): CallCenterContact
    {
        $externalId = 'tg_'.$row->chat_id.'_'.$row->message_id;
        $existing = CallCenterContact::query()->where('external_id', $externalId)->first();
        if ($existing) {
            return $existing;
        }

        $label = $row->senderLabel();
        $body = $row->displayText();

        return CallCenterContact::create([
            'external_id' => $externalId,
            'client_id' => $client?->id,
            'channel' => 'telegram',
            'direction' => 'incoming',
            'contact_date' => $row->message_date ?? now(),
            'contact_phone' => null,
            'contact_name' => $label,
            'notes' => mb_substr($body, 0, 2000),
            'outcome' => null,
            'created_by' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?int}
     */
    private function extractAttachment(array $message): array
    {
        if (isset($message['document']) && is_array($message['document'])) {
            $doc = $message['document'];

            return [
                'document',
                isset($doc['file_id']) ? (string) $doc['file_id'] : null,
                isset($doc['file_unique_id']) ? (string) $doc['file_unique_id'] : null,
                isset($doc['file_name']) ? (string) $doc['file_name'] : null,
                isset($doc['mime_type']) ? (string) $doc['mime_type'] : null,
                isset($doc['file_size']) ? (int) $doc['file_size'] : null,
            ];
        }
        if (isset($message['photo']) && is_array($message['photo']) && $message['photo'] !== []) {
            $photo = end($message['photo']);
            if (is_array($photo)) {
                return [
                    'photo',
                    isset($photo['file_id']) ? (string) $photo['file_id'] : null,
                    isset($photo['file_unique_id']) ? (string) $photo['file_unique_id'] : null,
                    null,
                    null,
                    isset($photo['file_size']) ? (int) $photo['file_size'] : null,
                ];
            }
        }

        return [null, null, null, null, null, null];
    }
}
