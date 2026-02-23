<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        if (Store::exists()) {
            return;
        }
        Store::create([
            'name' => 'Магазин №1',
            'address' => 'ул. Примерная, 1',
            'phone' => '+7 (495) 000-00-00',
            'is_active' => true,
        ]);
    }
}
