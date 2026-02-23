<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Кассовый документ (приход/расход). */
class CashDocument extends Model
{
    protected $fillable = [
        'store_id',
        'target_store_id',
        'client_id',
        'operation_type_id',
        'document_number',
        'document_date',
        'amount',
        'comment',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function targetStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'target_store_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function operationType(): BelongsTo
    {
        return $this->belongsTo(CashOperationType::class, 'operation_type_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isIncome(): bool
    {
        return $this->operationType && $this->operationType->direction === 'income';
    }

    public function isExpense(): bool
    {
        return $this->operationType && $this->operationType->direction === 'expense';
    }

    public function isTransfer(): bool
    {
        return $this->target_store_id !== null;
    }

    /** Генерация номера документа. */
    public static function generateDocumentNumber(int $storeId, string $direction): string
    {
        $prefix = $direction === 'income' ? 'ПКО' : 'РКО';
        $year = date('Y');
        $last = static::where('store_id', $storeId)
            ->whereHas('operationType', fn ($q) => $q->where('direction', $direction))
            ->whereYear('document_date', $year)
            ->max('id');
        $num = ($last ?? 0) + 1;
        return sprintf('%s-%s-%04d', $prefix, $year, $num);
    }
}
