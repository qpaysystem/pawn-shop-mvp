<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Событие по товару из опердня 1С (перемещение, смена статуса и др.). */
class LmbProductEvent extends Model
{
    public const TYPE_MOVE = 'move';

    public const TYPE_MOVE_PENDING = 'move_pending';

    public const TYPE_STATUS = 'status';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'external_id',
        'event_type',
        'event_number',
        'event_at',
        'item_id',
        'from_store_id',
        'to_store_id',
        'status_name',
        'status_id',
        'responsible',
        'executor',
        'quantity',
        'description',
        'source_doc_ref',
        'applied',
        'payload',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'event_at' => 'datetime',
        'applied' => 'boolean',
        'payload' => 'array',
        'quantity' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ItemStatus::class, 'status_id');
    }

    public function typeLabel(): string
    {
        return match ($this->event_type) {
            self::TYPE_MOVE => 'Перемещение',
            self::TYPE_MOVE_PENDING => 'К перемещению',
            self::TYPE_STATUS => 'Смена статуса',
            default => $this->event_type,
        };
    }
}
