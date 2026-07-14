<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Чат Avito Messenger (локальный inbox). */
class AvitoChat extends Model
{
    protected $fillable = [
        'branch_slug',
        'chat_id',
        'peer_name',
        'item_id',
        'item_title',
        'item_price',
        'item_url',
        'last_message',
        'last_at',
        'unread_count',
        'payload',
    ];

    protected $casts = [
        'last_at' => 'datetime',
        'payload' => 'array',
        'unread_count' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AvitoMessage::class, 'avito_chat_id')->orderBy('sent_at')->orderBy('id');
    }
}

