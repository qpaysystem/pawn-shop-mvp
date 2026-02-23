<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Обращение в компанию: звонок, мессенджер, соцсеть, визит. */
class CallCenterContact extends Model
{
    use HasFactory;

    public const CHANNELS = [
        'phone' => 'Телефон',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'vk' => 'ВКонтакте',
        'visit' => 'Личный визит',
        'other' => 'Другое',
    ];

    /** Статус вызова по MTS: принят / пропущен (только для загруженных из MTS). */
    public const CALL_STATUSES = [
        'placed' => 'Принят',
        'missed' => 'Пропущен',
    ];

    public const OUTCOMES = [
        'new' => 'Новое',
        'callback' => 'Обратный звонок',
        'visit_scheduled' => 'Визит запланирован',
        'converted_pawn' => 'Сделка: залог',
        'converted_purchase' => 'Сделка: скупка',
        'converted_commission' => 'Сделка: комиссия',
        'closed' => 'Закрыто',
    ];

    protected $fillable = [
        'client_id', 'channel', 'direction', 'call_status', 'call_duration_sec', 'store_id', 'contact_date',
        'contact_phone', 'contact_name', 'notes', 'outcome',
        'pawn_contract_id', 'purchase_contract_id', 'commission_contract_id',
        'created_by', 'external_id', 'ext_tracking_id', 'recording_path', 'recording_transcript',
    ];

    protected function casts(): array
    {
        return ['contact_date' => 'datetime'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function pawnContract()
    {
        return $this->belongsTo(PawnContract::class);
    }

    public function purchaseContract()
    {
        return $this->belongsTo(PurchaseContract::class);
    }

    public function commissionContract()
    {
        return $this->belongsTo(CommissionContract::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getChannelLabelAttribute(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }

    public function getOutcomeLabelAttribute(): string
    {
        return self::OUTCOMES[$this->outcome] ?? $this->outcome ?? '—';
    }

    public function getCallStatusLabelAttribute(): ?string
    {
        return $this->call_status ? (self::CALL_STATUSES[$this->call_status] ?? $this->call_status) : null;
    }

    /** Есть ли запись разговора (локальный файл или идентификатор MTS). */
    public function hasRecording(): bool
    {
        return ! empty($this->recording_path) || ! empty($this->ext_tracking_id);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->client_id) {
            return $this->client->full_name ?? '—';
        }

        return $this->contact_name ?: ($this->contact_phone ?: 'Не указано');
    }

    public function getLinkedContractAttribute(): ?Model
    {
        if ($this->pawn_contract_id) {
            return $this->pawnContract;
        }
        if ($this->purchase_contract_id) {
            return $this->purchaseContract;
        }
        if ($this->commission_contract_id) {
            return $this->commissionContract;
        }

        return null;
    }
}
