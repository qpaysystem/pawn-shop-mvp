<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->string('external_id', 128)->nullable()->after('id');
        });
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->unique('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
