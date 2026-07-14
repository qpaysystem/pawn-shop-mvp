<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Документ реализации (продажа товара). */
class SaleContract extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (SaleContract $doc) {
            LedgerEntry::where('document_type', 'sale_contract')->where('document_id', $doc->id)->delete();
        });
    }

    protected $fillable = [
        'contract_number', 'external_id', 'lmb_data', 'client_id', 'item_id', 'store_id', 'sold_by',
        'sale_amount', 'sale_date',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'sale_amount' => 'decimal:2',
        'lmb_data' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function soldByUser()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }
}
