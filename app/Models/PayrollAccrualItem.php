<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Строка начисления ФОТ по сотруднику. */
class PayrollAccrualItem extends Model
{
    protected $fillable = ['payroll_accrual_id', 'employee_id', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payrollAccrual(): BelongsTo
    {
        return $this->belongsTo(PayrollAccrual::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
