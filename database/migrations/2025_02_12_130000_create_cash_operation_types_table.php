<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_operation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('direction', ['income', 'expense']); // приход / расход
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $types = [
            ['name' => 'Выдача займа', 'direction' => 'expense', 'sort_order' => 1],
            ['name' => 'Возврат займа', 'direction' => 'income', 'sort_order' => 2],
            ['name' => 'Оплата от покупателя', 'direction' => 'income', 'sort_order' => 3],
            ['name' => 'Возврат покупателю', 'direction' => 'expense', 'sort_order' => 4],
            ['name' => 'Займ от учредителя', 'direction' => 'income', 'sort_order' => 5],
            ['name' => 'Оплата продавцу', 'direction' => 'expense', 'sort_order' => 6],
            ['name' => 'Выдача заработной платы', 'direction' => 'expense', 'sort_order' => 7],
        ];
        foreach ($types as $t) {
            DB::table('cash_operation_types')->insert([
                'name' => $t['name'],
                'direction' => $t['direction'],
                'sort_order' => $t['sort_order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_operation_types');
    }
};
