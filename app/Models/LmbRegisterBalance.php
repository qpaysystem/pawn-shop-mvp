<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Остаток по регистру накопления 1С (синхронизировано из _accumrg*). */
class LmbRegisterBalance extends Model
{
    protected $table = 'lmb_register_balances';

    protected $fillable = [
        'register_name', 'dimension_key', 'store_id', 'item_id',
        'quantity', 'amount', 'raw_dimensions', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'amount' => 'decimal:2',
            'raw_dimensions' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
