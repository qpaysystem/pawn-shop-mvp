<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'telegram_id')) {
                $table->unsignedBigInteger('telegram_id')->nullable()->index()->after('email');
            }
            if (! Schema::hasColumn('clients', 'telegram_username')) {
                $table->string('telegram_username', 100)->nullable()->index()->after('telegram_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'telegram_username')) {
                $table->dropColumn('telegram_username');
            }
            if (Schema::hasColumn('clients', 'telegram_id')) {
                $table->dropColumn('telegram_id');
            }
        });
    }
};
