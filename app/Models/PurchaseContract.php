<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Договор скупки (выкуп товара у клиента). */
class PurchaseContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_number', 'client_id', 'item_id', 'appraiser_id', 'store_id',
        'purchase_amount', 'purchase_date',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_amount' => 'decimal:2',
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

    /** Номер договора: S-2024-00001 (S — скупка). */
    public static function generateContractNumber(): string
    {
        $year = date('Y');
        $last = self::where('contract_number', 'like', "S-{$year}-%")
            ->orderByDesc('id')
            ->value('contract_number');
        $num = 1;
        if ($last && preg_match('/S-\d{4}-(\d+)/', $last, $m)) {
            $num = (int) $m[1] + 1;
        }
        return sprintf('S-%s-%05d', $year, $num);
    }
}
