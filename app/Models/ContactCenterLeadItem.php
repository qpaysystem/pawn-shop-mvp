<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactCenterLeadItem extends Model
{
    protected $fillable = [
        'lead_id',
        'title',
        'description',
        'expected_price',
        'appraised_from',
        'appraised_to',
        'photos',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'expected_price' => 'decimal:2',
            'appraised_from' => 'decimal:2',
            'appraised_to' => 'decimal:2',
            'photos' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(ContactCenterLead::class, 'lead_id');
    }
}
