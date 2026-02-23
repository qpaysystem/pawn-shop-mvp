<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Вид кассовой операции (приход/расход). */
class CashOperationType extends Model
{
    protected $fillable = ['name', 'direction', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cashDocuments(): HasMany
    {
        return $this->hasMany(CashDocument::class, 'operation_type_id');
    }

    public function isIncome(): bool
    {
        return $this->direction === 'income';
    }

    public function isExpense(): bool
    {
        return $this->direction === 'expense';
    }

    public static function incomeTypes()
    {
        return static::where('direction', 'income')->where('is_active', true)->orderBy('sort_order')->get();
    }

    public static function expenseTypes()
    {
        return static::where('direction', 'expense')->where('is_active', true)->orderBy('sort_order')->get();
    }

    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }
}
