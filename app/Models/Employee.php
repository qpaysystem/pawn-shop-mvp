<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Сотрудник (для ФОТ). */
class Employee extends Model
{
    protected $fillable = ['last_name', 'first_name', 'patronymic', 'position', 'store_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->last_name, $this->first_name, $this->patronymic])));
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function payrollAccrualItems(): HasMany
    {
        return $this->hasMany(PayrollAccrualItem::class, 'employee_id');
    }
}
