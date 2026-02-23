<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Документ начисления ФОТ. */
class PayrollAccrual extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (PayrollAccrual $doc) {
            LedgerEntry::where('document_type', 'payroll_accrual')->where('document_id', $doc->id)->delete();
        });
    }

    protected $fillable = ['number', 'period_month', 'period_year', 'accrual_date', 'total_amount', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'accrual_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollAccrualItem::class, 'payroll_accrual_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPeriodLabelAttribute(): string
    {
        $months = [1 => 'январь', 2 => 'февраль', 3 => 'март', 4 => 'апрель', 5 => 'май', 6 => 'июнь',
            7 => 'июль', 8 => 'август', 9 => 'сентябрь', 10 => 'октябрь', 11 => 'ноябрь', 12 => 'декабрь'];
        return ($months[$this->period_month] ?? $this->period_month) . ' ' . $this->period_year;
    }
}
