<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Товар. */
class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'metal', 'sample', 'weight_grams', 'category_id', 'brand_id', 'store_id',
        'storage_location_id', 'status_id', 'barcode', 'lmb_ref', 'photos',
        'initial_price', 'current_price',
    ];

    protected function casts(): array
    {
        return [
            'initial_price' => 'decimal:2',
            'current_price' => 'decimal:2',
            'weight_grams' => 'decimal:3',
        ];
    }

    /** Нормализация photos: web json_encode + mobile API, в т.ч. legacy double-JSON. */
    protected function photos(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::normalizePhotos($value),
            set: fn ($value) => self::encodePhotos($value),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function normalizePhotos(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($path) => is_string($path) && $path !== ''));
        }
        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($path) => is_string($path) && $path !== ''));
        }
        if (is_string($decoded)) {
            $again = json_decode($decoded, true);

            return is_array($again)
                ? array_values(array_filter($again, fn ($path) => is_string($path) && $path !== ''))
                : [];
        }

        return [];
    }

    public static function encodePhotos(mixed $value): ?string
    {
        $paths = self::normalizePhotos($value);

        return $paths === [] ? null : json_encode($paths, JSON_UNESCAPED_UNICODE);
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

    public function saleContract()
    {
        return $this->hasOne(SaleContract::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(ItemStatusHistory::class)->orderByDesc('created_at');
    }

    public function reservations()
    {
        return $this->hasMany(ItemReservation::class)->orderByDesc('created_at');
    }

    public function contactCenterLeads()
    {
        return $this->hasMany(ContactCenterLead::class)->orderByDesc('created_at');
    }

    public function priceAdjustments()
    {
        return $this->hasMany(ItemPriceAdjustment::class)->orderByDesc('created_at');
    }

    /** Генерация уникального штрихкода. */
    public static function generateBarcode(): string
    {
        do {
            $code = 'I'.date('Ymd').strtoupper(Str::random(6));
        } while (self::where('barcode', $code)->exists());

        return $code;
    }
}
