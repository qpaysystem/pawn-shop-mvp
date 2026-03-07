<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrafficSource extends Model
{
    protected $fillable = ['name', 'code', 'sort_order'];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'traffic_source_id');
    }
}
