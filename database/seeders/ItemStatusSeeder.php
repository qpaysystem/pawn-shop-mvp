<?php

namespace Database\Seeders;

use App\Models\ItemStatus;
use Illuminate\Database\Seeder;

class ItemStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Принят в ломбард', 'color' => '#17a2b8'],
            ['name' => 'На витрине', 'color' => '#28a745'],
            ['name' => 'Продан', 'color' => '#6f42c1'],
            ['name' => 'Выкуплен', 'color' => '#20c997'],
            ['name' => 'Не выкуплен', 'color' => '#dc3545'],
        ];
        foreach ($statuses as $s) {
            ItemStatus::firstOrCreate(['name' => $s['name']], $s);
        }
    }
}
