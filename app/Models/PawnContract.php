<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Договор залога. */
class PawnContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_number', 'client_id', 'item_id', 'appraiser_id', 'store_id',
        'loan_amount', 'loan_percent', 'loan_date', 'expiry_date', 'buyback_amount',
        'is_redeemed', 'redeemed_at', 'redeemed_by',
    ];

    protected function casts(): array
    {
        return [
            'loan_date' => 'date',
            'expiry_date' => 'date',
            'redeemed_at' => 'datetime',
            'loan_amount' => 'decimal:2',
            'loan_percent' => 'decimal:2',
            'buyback_amount' => 'decimal:2',
            'is_redeemed' => 'boolean',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function appraiser()
    {
        return $this->belongsTo(User::class, 'appraiser_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function redeemedByUser()
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    /** Сумма на выкуп: loan_amount + процент, по условиям займа. */
    public function getRedemptionAmountAttribute(): float
    {
        $stored = $this->attributes['buyback_amount'] ?? null;
        if ($stored !== null && (float) $stored > 0) {
            return (float) $stored;
        }
        $amount = (float) ($this->attributes['loan_amount'] ?? 0);
        $percent = (float) ($this->attributes['loan_percent'] ?? 0);

        return round($amount + ($amount * $percent / 100), 2);
    }

    /** Генерация номера договора: L-2024-00001 */
    public static function generateContractNumber(): string
    {
        $year = date('Y');
        $last = self::where('contract_number', 'like', "L-{$year}-%")
            ->orderByDesc('id')
            ->value('contract_number');
        $num = 1;
        if ($last && preg_match('/L-\d{4}-(\d+)/', $last, $m)) {
            $num = (int) $m[1] + 1;
        }
        return sprintf('L-%s-%05d', $year, $num);
    }
}
