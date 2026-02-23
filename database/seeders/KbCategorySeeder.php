<?php

namespace Database\Seeders;

use App\Models\KbCategory;
use Illuminate\Database\Seeder;

class KbCategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Обучение персонала', 'slug' => 'training', 'description' => 'Инструкции и материалы для обучения новых сотрудников', 'sort_order' => 0],
            ['name' => 'Регламенты и инструкции', 'slug' => 'regulations', 'description' => 'Регламентные документы и внутренние инструкции', 'sort_order' => 1],
        ];
        foreach ($items as $item) {
            KbCategory::firstOrCreate(['slug' => $item['slug']], array_merge($item, ['is_published' => true]));
        }
    }
}
