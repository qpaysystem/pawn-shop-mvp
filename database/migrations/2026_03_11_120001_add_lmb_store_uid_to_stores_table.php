<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Код склада/филиала в 1С для маппинга при синхронизации залогов и скупки. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'lmb_store_uid')) {
                $table->string('lmb_store_uid', 100)->nullable()->after('is_active')->comment('Код склада/филиала в 1С (encode(_idrref,\'hex\'))');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('lmb_store_uid');
        });
    }
};
