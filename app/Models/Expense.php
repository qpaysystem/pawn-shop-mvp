<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Документ начисления расхода. */
class Expense extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Expense $doc) {
            LedgerEntry::where('document_type', 'expense')->where('document_id', $doc->id)->delete();
        });
    }

    protected $fillable = ['number', 'expense_type_id', 'store_id', 'client_id', 'amount', 'expense_date', 'description', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
