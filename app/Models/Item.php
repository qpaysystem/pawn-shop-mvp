<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Товар. */
class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'category_id', 'brand_id', 'store_id',
        'storage_location_id', 'status_id', 'barcode', 'photos',
        'initial_price', 'current_price',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'initial_price' => 'decimal:2',
            'current_price' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function storageLocation()
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function status()
    {
        return $this->belongsTo(ItemStatus::class, 'status_id');
    }

    public function pawnContract()
    {
        return $this->hasOne(PawnContract::class);
    }

    public function commissionContract()
    {
        return $this->hasOne(CommissionContract::class);
    }

    public function purchaseContract()
    {
        return $this->hasOne(PurchaseContract::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(ItemStatusHistory::class)->orderByDesc('created_at');
    }

    /** Генерация уникального штрихкода. */
    public static function generateBarcode(): string
    {
        do {
            $code = 'I' . date('Ymd') . strtoupper(Str::random(6));
        } while (self::where('barcode', $code)->exists());
        return $code;
    }
}
