<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Договор комиссии. */
class CommissionContract extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (CommissionContract $doc) {
            LedgerEntry::where('document_type', 'commission_contract')->where('document_id', $doc->id)->delete();
        });
    }

    protected $fillable = [
        'contract_number', 'client_id', 'item_id', 'appraiser_id', 'store_id',
        'commission_percent', 'commission_amount', 'seller_price', 'client_price',
        'expiry_date', 'is_sold', 'sold_at', 'sold_by', 'client_paid',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'sold_at' => 'datetime',
            'commission_percent' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'seller_price' => 'decimal:2',
            'client_price' => 'decimal:2',
            'is_sold' => 'boolean',
            'client_paid' => 'boolean',
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

    public function soldByUser()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /** Номер договора: C-2024-00001 */
    public static function generateContractNumber(): string
    {
        $year = date('Y');
        $last = self::where('contract_number', 'like', "C-{$year}-%")
            ->orderByDesc('id')
            ->value('contract_number');
        $num = 1;
        if ($last && preg_match('/C-\d{4}-(\d+)/', $last, $m)) {
            $num = (int) $m[1] + 1;
        }
        return sprintf('C-%s-%05d', $year, $num);
    }
}
