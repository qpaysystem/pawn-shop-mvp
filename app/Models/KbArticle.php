<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Статья базы знаний. */
class KbArticle extends Model
{
    protected $table = 'kb_articles';

    protected $fillable = ['category_id', 'title', 'slug', 'content', 'images', 'video_urls', 'created_by', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'images' => 'array',
            'video_urls' => 'array',
        ];
    }

    /** Всегда массив (для отображения на странице статьи). */
    public function getImagesAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** Всегда массив (ссылки на видео). */
    public function getVideoUrlsAttribute($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($u) => is_string($u) && $u !== ''));
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($u) => is_string($u) && $u !== ''));
            }
            // одна ссылка строкой (или после двойного кодирования)
            $trimmed = trim($value);
            if ($trimmed !== '' && (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://'))) {
                return [$trimmed];
            }
        }
        return [];
    }

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** URL для отображения прикреплённого фото (как в CRM: asset('storage/' . path)). */
    public function imageUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }
        $path = ltrim($path, '/');
        return asset('storage/' . $path);
    }

    public static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title);
            }
        });
    }
}
