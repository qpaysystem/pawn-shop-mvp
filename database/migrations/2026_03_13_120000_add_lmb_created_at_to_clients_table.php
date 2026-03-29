<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'lmb_created_at')) {
                $table->dateTime('lmb_created_at')->nullable()->after('lmb_full_name')->comment('Дата создания контрагента в 1С');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'lmb_created_at')) {
                $table->dropColumn('lmb_created_at');
            }
        });
    }
};
