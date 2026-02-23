<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->unsignedInteger('call_duration_sec')->nullable()->after('call_status');
        });
    }

    public function down(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->dropColumn('call_duration_sec');
        });
    }
};
