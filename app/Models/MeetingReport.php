<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Журнал отчётов по видеособраниям (conf.nnfm.pro). */
class MeetingReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'business_date',
        'meeting_at',
        'room',
        'title',
        'summary',
        'highlights',
        'transcript',
        'transcript_raw',
        'file_ref',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'meeting_at' => 'datetime',
        'highlights' => 'array',
        'processed_at' => 'datetime',
    ];

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
