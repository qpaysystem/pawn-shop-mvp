<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('accounts')->where('code', '60')->exists()) {
            return;
        }
        $maxOrder = (int) DB::table('accounts')->max('sort_order');
        DB::table('accounts')->insert([
            'code' => '60',
            'name' => 'Расчёты с поставщиками',
            'description' => 'Задолженность перед поставщиками (начисление расходов и т.п.)',
            'type' => 'passive',
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '60')->delete();
    }
};
