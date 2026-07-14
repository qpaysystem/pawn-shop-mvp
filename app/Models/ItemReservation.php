<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Бронь товара контакт-центром (1–5 дней). */
class ItemReservation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CONVERTED = 'converted';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Активна',
        self::STATUS_EXPIRED => 'Истекла',
        self::STATUS_CANCELLED => 'Отменена',
        self::STATUS_CONVERTED => 'Конвертирована',
    ];

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 5;

    protected $fillable = [
        'item_id',
        'lead_id',
        'client_id',
        'contact_name',
        'contact_phone',
        'status',
        'reserved_until',
        'notes',
        'created_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'reserved_until' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(ContactCenterLead::class, 'lead_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->reserved_until->isFuture();
    }
}
