<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Место хранения (склад, витрина). */
class StorageLocation extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'name'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'storage_location_id');
    }
}
