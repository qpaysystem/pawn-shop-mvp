<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_type', 20)->default('individual')->after('id'); // individual | legal
            $table->string('legal_name')->nullable()->after('patronymic'); // наименование организации
            $table->string('inn', 12)->nullable()->after('legal_name');
            $table->string('kpp', 9)->nullable()->after('inn');
            $table->text('legal_address')->nullable()->after('kpp'); // юридический адрес
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['client_type', 'legal_name', 'inn', 'kpp', 'legal_address']);
        });
    }
};
