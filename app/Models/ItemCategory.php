<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Категория товаров (древовидная). */
class ItemCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'evaluation_config'];

    protected function casts(): array
    {
        return ['evaluation_config' => 'array'];
    }

    public function parent()
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ItemCategory::class, 'parent_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }
}
