<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Вид расхода (справочник). */
class ExpenseType extends Model
{
    protected $fillable = ['name', 'code', 'account_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_type_id');
    }
}
