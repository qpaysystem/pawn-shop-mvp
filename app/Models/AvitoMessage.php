<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Сообщение Avito Messenger (локальный inbox). */
class AvitoMessage extends Model
{
    protected $fillable = [
        'avito_chat_id',
        'message_id',
        'direction',
        'type',
        'text',
        'sent_at',
        'call_center_contact_id',
        'payload',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AvitoChat::class, 'avito_chat_id');
    }

    public function callCenterContact(): BelongsTo
    {
        return $this->belongsTo(CallCenterContact::class, 'call_center_contact_id');
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'in';
    }
}

