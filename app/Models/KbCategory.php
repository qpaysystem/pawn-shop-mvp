<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Категория базы знаний (обучение персонала, регламенты). */
class KbCategory extends Model
{
    protected $table = 'kb_categories';

    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function articles()
    {
        return $this->hasMany(KbArticle::class, 'category_id')->orderBy('sort_order')->orderBy('title');
    }

    public function publishedArticles()
    {
        return $this->hasMany(KbArticle::class, 'category_id')->where('is_published', true)->orderBy('sort_order')->orderBy('title');
    }

    public static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }
}
