<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('id');
            $table->string('first_name')->nullable()->after('last_name');
            $table->string('patronymic')->nullable()->after('first_name');
        });

        // Миграция существующих full_name: Фамилия Имя Отчество
        $clients = DB::table('clients')->whereNotNull('full_name')->get();
        foreach ($clients as $c) {
            $parts = preg_split('/\s+/u', trim($c->full_name), 3, PREG_SPLIT_NO_EMPTY);
            $lastName = $parts[0] ?? null;
            $firstName = $parts[1] ?? null;
            $patronymic = $parts[2] ?? null;
            DB::table('clients')->where('id', $c->id)->update([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'patronymic' => $patronymic,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['last_name', 'first_name', 'patronymic']);
        });
    }
};
