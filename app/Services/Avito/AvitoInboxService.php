<?php

namespace App\Services\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\CallCenterContact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Сохранение Avito чатов/сообщений в локальный inbox и колл-центр. */
class AvitoInboxService
{
    /**
     * @param  array<string, mixed>  $chatMeta  Нормализованный чат из CallCenterAvitoService::listChats().
     */
    public function upsertChat(string $branchSlug, array $chatMeta): ?AvitoChat
    {
        $chatId = (string) ($chatMeta['chat_id'] ?? '');
        if ($chatId === '') {
            return null;
        }

        return AvitoChat::query()->updateOrCreate(
            ['chat_id' => $chatId],
            [
                'branch_slug' => $branchSlug,
                'peer_name' => (string) ($chatMeta['peer_name'] ?? $chatMeta['title'] ?? null),
                'item_id' => isset($chatMeta['item_id']) ? (string) $chatMeta['item_id'] : null,
                'item_title' => isset($chatMeta['item_title']) ? (string) $chatMeta['item_title'] : null,
                'item_price' => isset($chatMeta['item_price']) ? (string) $chatMeta['item_price'] : null,
                'item_url' => isset($chatMeta['item_url']) ? (string) $chatMeta['item_url'] : null,
                'last_message' => isset($chatMeta['last_message']) ? (string) $chatMeta['last_message'] : null,
                'last_at' => ! empty($chatMeta['last_at_ts']) ? Carbon::createFromTimestamp((int) $chatMeta['last_at_ts']) : null,
                'unread_count' => ! empty($chatMeta['unread']) ? 1 : 0,
                // Явно кодируем, чтобы не зависеть от кастов модели при updateOrCreate.
                'payload' => json_encode($chatMeta, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages  Нормализованные сообщения из CallCenterAvitoService::messagesForChat().
     */
    public function ingestMessages(string $branchSlug, array $chatMeta, array $messages): ?AvitoChat
    {
        return DB::transaction(function () use ($branchSlug, $chatMeta, $messages) {
            $chat = $this->upsertChat($branchSlug, $chatMeta);
            if (! $chat) {
                return null;
            }

            foreach ($messages as $msg) {
                if (! is_array($msg)) {
                    continue;
                }
                $messageId = (string) ($msg['id'] ?? '');
                if ($messageId === '') {
                    continue;
                }
                $direction = ! empty($msg['outgoing']) ? 'out' : 'in';
                $text = isset($msg['text']) ? trim((string) $msg['text']) : null;
                $sentAt = ! empty($msg['sort_ts']) ? Carbon::createFromTimestamp((int) $msg['sort_ts']) : null;

                $row = AvitoMessage::query()->firstOrCreate(
                    ['avito_chat_id' => $chat->id, 'message_id' => $messageId],
                    [
                        'direction' => $direction,
                        'type' => null,
                        'text' => $text,
                        'sent_at' => $sentAt,
                        'payload' => json_encode($msg, JSON_UNESCAPED_UNICODE),
                    ]
                );

                // Создаём обращение только для входящих сообщений (чтобы привязывать заявки).
                if ($direction === 'in' && $row->call_center_contact_id === null) {
                    $contact = $this->createCallCenterContact($chat, $row);
                    $row->call_center_contact_id = $contact->id;
                    $row->save();
                }
            }

            return $chat->fresh();
        });
    }

    public function recordOutgoing(string $branchSlug, array $chatMeta, ?string $messageId, string $text): void
    {
        $messageId = $messageId ? trim($messageId) : '';
        if ($messageId === '') {
            return;
        }

        $chat = $this->upsertChat($branchSlug, $chatMeta);
        if (! $chat) {
            return;
        }

        AvitoMessage::query()->updateOrCreate(
            ['avito_chat_id' => $chat->id, 'message_id' => $messageId],
            [
                'direction' => 'out',
                'type' => 'text',
                'text' => trim($text),
                'sent_at' => now(),
                'payload' => json_encode(['id' => $messageId, 'outgoing' => true, 'text' => trim($text), 'sort_ts' => time()], JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function createCallCenterContact(AvitoChat $chat, AvitoMessage $message): CallCenterContact
    {
        $externalId = 'avito_'.$chat->chat_id.'_'.$message->message_id;
        $existing = CallCenterContact::query()->where('external_id', $externalId)->first();
        if ($existing) {
            return $existing;
        }

        $who = trim((string) ($chat->peer_name ?? 'Покупатель'));
        $notes = trim((string) ($message->text ?? ''));
        if ($notes === '') {
            $notes = '[message]';
        }

        $meta = [];
        if ($chat->item_title) {
            $meta[] = 'Объявление: '.$chat->item_title;
        }
        if ($chat->item_price) {
            $meta[] = 'Цена: '.$chat->item_price;
        }
        if ($chat->item_url) {
            $meta[] = 'URL: '.$chat->item_url;
        }

        $fullNotes = trim(implode("\n", array_filter([$notes, $meta ? implode(' · ', $meta) : null])));

        return CallCenterContact::create([
            'external_id' => $externalId,
            'client_id' => null,
            'channel' => 'avito',
            'direction' => 'incoming',
            'contact_date' => $message->sent_at ?? now(),
            'contact_phone' => null,
            'contact_name' => $who !== '' ? $who : 'Avito',
            'notes' => mb_substr($fullNotes, 0, 2000),
            'outcome' => null,
            'created_by' => null,
        ]);
    }
}

