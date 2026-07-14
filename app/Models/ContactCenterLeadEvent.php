<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactCenterLeadEvent extends Model
{
    public const EVENT_CREATED = 'created';

    public const EVENT_STATUS = 'status_change';

    public const EVENT_NOTE = 'note';

    public const EVENT_ASSIGNMENT = 'assignment';

    public const EVENT_TYPES = [
        self::EVENT_CREATED => 'Создана',
        self::EVENT_STATUS => 'Смена статуса',
        self::EVENT_NOTE => 'Заметка',
        self::EVENT_ASSIGNMENT => 'Назначение',
    ];

    protected $fillable = [
        'lead_id',
        'channel',
        'event_type',
        'message',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(ContactCenterLead::class, 'lead_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }
}
