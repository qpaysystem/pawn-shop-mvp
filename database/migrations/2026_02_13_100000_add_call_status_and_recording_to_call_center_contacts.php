<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->string('call_status', 16)->nullable()->after('direction'); // placed = принят, missed = пропущен (только для MTS)
            $table->string('ext_tracking_id', 128)->nullable()->after('external_id'); // MTS extTrackingId для записи разговора
            $table->string('recording_path', 512)->nullable()->after('ext_tracking_id'); // путь к файлу записи (mp3)
        });
    }

    public function down(): void
    {
        Schema::table('call_center_contacts', function (Blueprint $table) {
            $table->dropColumn(['call_status', 'ext_tracking_id', 'recording_path']);
        });
    }
};
