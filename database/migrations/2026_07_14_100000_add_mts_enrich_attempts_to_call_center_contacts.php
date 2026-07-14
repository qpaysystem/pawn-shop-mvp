<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->unsignedTinyInteger('mts_enrich_attempts')->default(0)->after('portal_pushed_at');
            $table->timestamp('mts_enrich_next_at')->nullable()->index()->after('mts_enrich_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->dropColumn(['mts_enrich_attempts', 'mts_enrich_next_at']);
        });
    }
};
