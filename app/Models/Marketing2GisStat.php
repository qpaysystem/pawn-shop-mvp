<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Статистика 2ГИС за день (просмотры карточки, звонки). Заполняется вручную или импортом — в API 2ГИС этих данных нет. */
class Marketing2GisStat extends Model
{
    protected $table = 'marketing_2gis_stats';

    protected $fillable = ['date', 'views_count', 'calls_count', 'comment'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
