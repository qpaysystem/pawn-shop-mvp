<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Статус товара. */
class ItemStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color'];

    public function items()
    {
        return $this->hasMany(Item::class, 'status_id');
    }
}
