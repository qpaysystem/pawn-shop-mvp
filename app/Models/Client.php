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

    protected $fillable = [
        'client_type', 'full_name', 'last_name', 'first_name', 'patronymic',
        'legal_name', 'inn', 'kpp', 'legal_address',
        'phone', 'email', 'passport_data', 'notes', 'blacklist_flag',
        'lmb_data',
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

    protected function casts(): array
    {
        return [
            'blacklist_flag' => 'boolean',
            'lmb_data' => 'array',
        ];
    }

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
