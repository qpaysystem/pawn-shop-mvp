<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StoreSeeder::class,
            ItemStatusSeeder::class,
            StorageLocationSeeder::class,
            UserSeeder::class,
            KbCategorySeeder::class,
            AccountsSeeder::class,
        ]);
    }
}
