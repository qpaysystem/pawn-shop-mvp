<?php

namespace App\Models;

use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/** Входящее/исходящее сообщение Telegram (inbox ломбарда). */
class TelegramMessage extends Model
{
    protected $fillable = [
        'chat_id',
        'message_id',
        'chat_type',
        'message_type',
        'from_user_id',
        'from_username',
        'from_first_name',
        'text',
        'caption',
        'file_id',
        'file_unique_id',
        'file_name',
        'mime_type',
        'file_size',
        'message_date',
        'client_id',
        'call_center_contact_id',
        'portal_pushed_at',
    ];

    protected $casts = [
        'message_date' => 'datetime',
        'portal_pushed_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function callCenterContact(): BelongsTo
    {
        return $this->belongsTo(CallCenterContact::class);
    }

    public function externalId(): string
    {
        return 'lombard-tg:'.$this->chat_id.':'.$this->message_id;
    }

    public function displayText(): string
    {
        $text = trim((string) ($this->text ?? ''));
        $caption = trim((string) ($this->caption ?? ''));
        if ($text !== '') {
            return $text;
        }
        if ($caption !== '') {
            return $caption;
        }
        $type = (string) ($this->message_type ?? '');
        $fileName = (string) ($this->file_name ?? '');
        if ($type === 'photo') {
            return 'Фото без подписи';
        }
        if ($type === 'document') {
            return 'Документ'.($fileName !== '' ? ': '.$fileName : '');
        }

        return $fileName !== '' ? 'Файл: '.$fileName : '';
    }

    public function senderLabel(): string
    {
        $name = trim((string) ($this->from_first_name ?? ''));
        if ($name !== '') {
            return $name;
        }
        if ($this->from_username) {
            return '@'.ltrim((string) $this->from_username, '@');
        }

        return $this->from_user_id ? 'user:'.$this->from_user_id : 'unknown';
    }

    public static function recordBotOutgoing(
        string $chatId,
        ?int $telegramMessageId,
        string $text,
        string $author = 'ИИ-агент'
    ): void {
        $chatId = TelegramService::normalizeChatIdForStorage($chatId);
        $mid = $telegramMessageId ?? self::syntheticOutgoingMessageId();

        try {
            self::query()->firstOrCreate(
                [
                    'chat_id' => $chatId,
                    'message_id' => $mid,
                ],
                [
                    'chat_type' => 'private',
                    'from_user_id' => null,
                    'from_username' => null,
                    'from_first_name' => $author,
                    'text' => $text,
                    'message_date' => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('telegram_bot_message_store', ['error' => $e->getMessage()]);
        }
    }

    private static function syntheticOutgoingMessageId(): int
    {
        return (int) (9000000000000000 + ((int) (microtime(true) * 1000000) % 1000000000000) + random_int(0, 999999));
    }
}
