<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Поля для данных из 1С: код контрагента (user_uid) и ФИО из 1С (lmb_full_name).
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('user_uid', 100)->nullable()->after('lmb_data')->comment('Код контрагента в 1С');
            $table->string('lmb_full_name')->nullable()->after('user_uid')->comment('ФИО из 1С (first_name в ответе API)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['user_uid', 'lmb_full_name']);
        });
    }
};
