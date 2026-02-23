<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_documents', function (Blueprint $table) {
            $table->foreignId('target_store_id')->nullable()->after('store_id')->constrained('stores')->nullOnDelete();
        });

        DB::table('cash_operation_types')->insert([
            'name' => 'Перемещение между кассами',
            'direction' => 'expense',
            'sort_order' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('cash_operation_types')->where('name', 'Перемещение между кассами')->delete();
        Schema::table('cash_documents', function (Blueprint $table) {
            $table->dropForeign(['target_store_id']);
        });
    }
};
