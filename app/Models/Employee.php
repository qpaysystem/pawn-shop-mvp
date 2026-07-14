<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Сотрудник (для ФОТ). */
class Employee extends Model
{
    protected $fillable = [
        'last_name',
        'first_name',
        'patronymic',
        'phone',
        'passport_data',
        'photo_path',
        'registration_address',
        'position',
        'store_id',
        'user_id',
        'telegram',
        'character_description',
        'professional_data',
        'is_active',
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return null;
        }

        return asset('storage/' . ltrim($this->photo_path, '/'));
    }

    public function payrollAccrualItems(): HasMany
    {
        return $this->hasMany(PayrollAccrualItem::class, 'employee_id');
    }

    public function managementTasks(): HasMany
    {
        return $this->hasMany(ManagementTask::class, 'employee_id');
    }
}
