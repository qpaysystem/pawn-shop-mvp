<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Задача управления (постановка сотруднику). */
class ManagementTask extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_NEW => 'Новая',
        self::STATUS_IN_PROGRESS => 'В работе',
        self::STATUS_DONE => 'Выполнена',
        self::STATUS_CANCELLED => 'Отменена',
    ];

    /** Порядок колонок канбана. */
    public const KANBAN_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'management_tasks';

    protected $fillable = [
        'title',
        'description',
        'employee_id',
        'status',
        'starts_at',
        'due_at',
        'created_by',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_at' => 'date',
        'due_at' => 'date',
        'sort_order' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'text-bg-secondary',
            self::STATUS_IN_PROGRESS => 'text-bg-primary',
            self::STATUS_DONE => 'text-bg-success',
            self::STATUS_CANCELLED => 'text-bg-dark',
            default => 'text-bg-secondary',
        };
    }

    public function isOverdue(): bool
    {
        if ($this->due_at === null || $this->due_at === '') {
            return false;
        }
        if (in_array($this->status, [self::STATUS_DONE, self::STATUS_CANCELLED], true)) {
            return false;
        }

        $due = $this->due_at instanceof \Carbon\CarbonInterface
            ? $this->due_at
            : \Carbon\Carbon::parse($this->due_at);

        return $due->lt(now()->startOfDay());
    }
}
