<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Магазин (точка сети). */
class Store extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function storageLocations()
    {
        return $this->hasMany(StorageLocation::class, 'store_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'store_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'store_id');
    }

    public function cashDocuments()
    {
        return $this->hasMany(CashDocument::class);
    }

    /** Кассовый баланс (приходы минус расходы, включая перемещения). */
    public function getCashBalanceAttribute(): float
    {
        $income = $this->cashDocuments()
            ->whereHas('operationType', fn ($q) => $q->where('direction', 'income'))
            ->sum('amount');
        $incomingTransfers = CashDocument::where('target_store_id', $this->id)->sum('amount');
        $expense = $this->cashDocuments()
            ->whereHas('operationType', fn ($q) => $q->where('direction', 'expense'))
            ->sum('amount');
        return round((float) $income + (float) $incomingTransfers - (float) $expense, 2);
    }
}
