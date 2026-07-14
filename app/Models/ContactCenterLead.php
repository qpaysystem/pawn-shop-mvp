<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Заявка контакт-центра (черновик на оценку / залог / скупку / продажу). */
class ContactCenterLead extends Model
{
    public const TYPE_ESTIMATE = 'estimate';

    public const TYPE_PAWN_DRAFT = 'pawn_draft';

    public const TYPE_PURCHASE_DRAFT = 'purchase_draft';

    public const TYPE_SALE_REQUEST = 'sale_request';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_WORK = 'in_work';

    public const STATUS_WAITING_CLIENT = 'waiting_client';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ASSIGNED = 'assigned_to_store';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_CLOSED_LOST = 'closed_lost';

    public const STATUS_SPAM = 'spam';

    public const TYPES = [
        self::TYPE_ESTIMATE => 'Оценка',
        self::TYPE_PAWN_DRAFT => 'Черновик залога',
        self::TYPE_PURCHASE_DRAFT => 'Черновик скупки',
        self::TYPE_SALE_REQUEST => 'Заявка на продажу',
    ];

    public const STATUSES = [
        self::STATUS_NEW => 'Новая',
        self::STATUS_IN_WORK => 'В работе',
        self::STATUS_WAITING_CLIENT => 'Ожидает клиента',
        self::STATUS_SCHEDULED => 'Визит назначен',
        self::STATUS_ASSIGNED => 'Передана в точку',
        self::STATUS_RESERVED => 'Товар забронирован',
        self::STATUS_CONVERTED => 'Конвертирована',
        self::STATUS_CLOSED_LOST => 'Закрыта',
        self::STATUS_SPAM => 'Спам',
    ];

    public const CHANNELS = [
        'telegram' => 'Telegram',
        'avito' => 'Avito',
        'phone' => 'Телефон',
    ];

    public const LOST_REASONS = [
        'no_answer' => 'Не дозвонились',
        'price' => 'Не устроила цена',
        'changed_mind' => 'Передумал',
        'duplicate' => 'Дубликат',
        'other' => 'Другое',
    ];

    protected $fillable = [
        'lead_number',
        'type',
        'status',
        'source_channel',
        'client_id',
        'contact_name',
        'contact_phone',
        'store_id_target',
        'assignee_user_id',
        'item_id',
        'call_center_contact_id',
        'preferred_at',
        'notes',
        'lost_reason',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'preferred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function targetStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id_target');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function callCenterContact(): BelongsTo
    {
        return $this->belongsTo(CallCenterContact::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContactCenterLeadItem::class, 'lead_id')->orderBy('sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContactCenterLeadEvent::class, 'lead_id')->orderByDesc('created_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ItemReservation::class, 'lead_id')->orderByDesc('created_at');
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(ItemReservation::class, 'lead_id')
            ->where('status', ItemReservation::STATUS_ACTIVE)
            ->where('reserved_until', '>', now())
            ->latestOfMany();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->source_channel] ?? $this->source_channel;
    }

    public static function generateLeadNumber(): string
    {
        $year = date('Y');
        $last = self::query()
            ->where('lead_number', 'like', "ЗК-{$year}-%")
            ->orderByDesc('id')
            ->value('lead_number');

        $num = 1;
        if ($last && preg_match('/ЗК-\d{4}-(\d+)/', $last, $m)) {
            $num = (int) $m[1] + 1;
        }

        return sprintf('ЗК-%s-%05d', $year, $num);
    }
}
