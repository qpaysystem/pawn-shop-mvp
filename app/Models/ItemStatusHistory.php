<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Запись истории смены статуса товара. */
class ItemStatusHistory extends Model
{
    /** Имя таблицы в БД (миграция создала singular). */
    protected $table = 'item_status_history';

    public $timestamps = false;

    protected $fillable = ['item_id', 'old_status_id', 'new_status_id', 'changed_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function oldStatus()
    {
        return $this->belongsTo(ItemStatus::class, 'old_status_id');
    }

    public function newStatus()
    {
        return $this->belongsTo(ItemStatus::class, 'new_status_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
