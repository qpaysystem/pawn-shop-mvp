<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->json('images')->nullable()->after('content')->comment('Пути к прикреплённым фото');
            $table->json('video_urls')->nullable()->after('images')->comment('Ссылки на видео (YouTube, Vimeo и др.)');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn(['images', 'video_urls']);
        });
    }
};
