<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Шаблон проводки для типа документа: настройка отражения в ОСВ. */
class DocumentLedgerTemplate extends Model
{
    protected $fillable = [
        'document_type',
        'name',
        'debit_account_code',
        'credit_account_code',
        'amount_field',
        'comment_template',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public static function forDocumentType(string $documentType)
    {
        return static::where('document_type', $documentType)->orderBy('sort_order')->orderBy('id')->get();
    }
}
