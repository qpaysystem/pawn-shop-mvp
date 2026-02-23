<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Расчётный счёт банка. */
class BankAccount extends Model
{
    protected $fillable = ['name', 'bank_name', 'account_number', 'bik', 'correspondent_account', 'store_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function bankStatements(): HasMany
    {
        return $this->hasMany(BankStatement::class, 'bank_account_id');
    }
}
