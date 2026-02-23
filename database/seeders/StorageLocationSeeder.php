<?php

namespace Database\Seeders;

use App\Models\StorageLocation;
use App\Models\Store;
use Illuminate\Database\Seeder;

class StorageLocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Store::all() as $store) {
            StorageLocation::firstOrCreate(['store_id' => $store->id, 'name' => 'Склад']);
            StorageLocation::firstOrCreate(['store_id' => $store->id, 'name' => 'Витрина-1']);
        }
    }
}
