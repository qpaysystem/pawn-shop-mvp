<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_reports', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
            $table->dateTime('meeting_at');
            $table->string('room', 256)->default('');
            $table->string('title', 512)->default('');
            $table->text('summary')->nullable();
            $table->json('highlights')->nullable();
            $table->longText('transcript')->nullable();
            $table->longText('transcript_raw')->nullable();
            $table->string('file_ref', 512);
            $table->string('status', 32)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('file_ref');
            $table->index('meeting_at');
            $table->index('business_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reports');
    }
};
