<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@example.com')->exists()) {
            return;
        }
        User::create([
            'name' => 'Супер-администратор',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super-admin',
            'store_id' => null,
        ]);

        $store = Store::first();
        if ($store && ! User::where('email', 'appraiser@example.com')->exists()) {
            User::create([
                'name' => 'Оценщик Тест',
                'email' => 'appraiser@example.com',
                'password' => Hash::make('password'),
                'role' => 'appraiser',
                'store_id' => $store->id,
            ]);
        }
    }
}
