<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('traffic_source_id')->nullable()->after('notes')->constrained('traffic_sources')->nullOnDelete();
            $table->string('funnel_stage', 50)->nullable()->after('traffic_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['traffic_source_id']);
            $table->dropColumn(['traffic_source_id', 'funnel_stage']);
        });
    }
};
