<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Строка выписки (движение по счёту). */
class BankStatementLine extends Model
{
    protected $fillable = ['bank_statement_id', 'line_date', 'amount', 'description', 'counterparty', 'document_number', 'external_id'];

    /** @var array<string, string> */
    protected $casts = [
        'line_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }
}
