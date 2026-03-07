<?php

namespace Database\Seeders;

use App\Models\TrafficSource;
use Illuminate\Database\Seeder;

class TrafficSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => '2ГИС', 'code' => '2gis', 'sort_order' => 10],
            ['name' => 'Яндекс', 'code' => 'yandex', 'sort_order' => 20],
            ['name' => 'Google', 'code' => 'google', 'sort_order' => 30],
            ['name' => 'Телефония', 'code' => 'telephony', 'sort_order' => 40],
            ['name' => 'Мессенджеры', 'code' => 'messengers', 'sort_order' => 50],
        ];

        foreach ($sources as $item) {
            TrafficSource::updateOrCreate(
                ['code' => $item['code']],
                ['name' => $item['name'], 'sort_order' => $item['sort_order']]
            );
        }
    }
}
