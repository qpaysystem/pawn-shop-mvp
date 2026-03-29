<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Клиент (залогодатель / комитент). */
class Client extends Model
{
    use HasFactory;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_LEGAL = 'legal';

    /** Этапы воронки для маркетинга */
    public const FUNNEL_LEAD = 'lead';

    public const FUNNEL_CONTACT = 'contact';

    public const FUNNEL_VISIT = 'visit';

    public const FUNNEL_DEAL = 'deal';

    protected $fillable = [
        'client_type', 'full_name', 'last_name', 'first_name', 'patronymic',
        'legal_name', 'inn', 'kpp', 'legal_address',
        'phone', 'phone_key', 'email', 'passport_data', 'notes', 'blacklist_flag',
        'lmb_data', 'user_uid', 'lmb_full_name', 'lmb_created_at',
        'traffic_source_id', 'funnel_stage',
        'lmb_identity_document_type', 'lmb_passport_issued_by', 'lmb_passport_issued_at', 'lmb_registration_address',
    ];

    public function getFullNameAttribute($value): string
    {
        if (($this->attributes['client_type'] ?? '') === self::TYPE_LEGAL) {
            $legal = trim((string) ($this->attributes['legal_name'] ?? ''));
            if ($legal !== '') {
                return $legal;
            }
        }
        $last = $this->attributes['last_name'] ?? '';
        $first = $this->attributes['first_name'] ?? '';
        $patr = $this->attributes['patronymic'] ?? '';
        $fromParts = trim(implode(' ', array_filter([$last, $first, $patr])));

        return $fromParts !== '' ? $fromParts : (string) ($value ?? '');
    }

    public function isLegal(): bool
    {
        return ($this->attributes['client_type'] ?? '') === self::TYPE_LEGAL;
    }

    /** Последние 7 цифр телефона для сопоставления с 1С (поиск по номеру без учёта формата). */
    public static function phoneToKey(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) >= 7 ? substr($digits, -7) : null;
    }

    /** Laravel 10: касты только через $casts (метод casts() из Laravel 11 не подхватывается). */
    protected $casts = [
        'blacklist_flag' => 'boolean',
        'lmb_data' => 'array',
        'lmb_created_at' => 'datetime',
        'lmb_passport_issued_at' => 'date',
    ];

    public function pawnContracts()
    {
        return $this->hasMany(PawnContract::class);
    }

    public function commissionContracts()
    {
        return $this->hasMany(CommissionContract::class);
    }

    public function purchaseContracts()
    {
        return $this->hasMany(PurchaseContract::class);
    }

    public function callCenterContacts()
    {
        return $this->hasMany(CallCenterContact::class);
    }

    public function trafficSource()
    {
        return $this->belongsTo(TrafficSource::class);
    }

    public static function funnelStageLabels(): array
    {
        return [
            self::FUNNEL_LEAD => 'Лид / Обращение',
            self::FUNNEL_CONTACT => 'Контакт',
            self::FUNNEL_VISIT => 'Визит',
            self::FUNNEL_DEAL => 'Сделка',
        ];
    }

    public function cashDocuments()
    {
        return $this->hasMany(CashDocument::class);
    }

    /** Кассовый баланс клиента: приходы минус расходы по операциям с этим клиентом. */
    public function getCashBalanceAttribute(): float
    {
        $income = $this->cashDocuments()
            ->whereHas('operationType', fn ($q) => $q->where('direction', 'income'))
            ->sum('amount');
        $expense = $this->cashDocuments()
            ->whereHas('operationType', fn ($q) => $q->where('direction', 'expense'))
            ->sum('amount');

        return round((float) $income - (float) $expense, 2);
    }
}
