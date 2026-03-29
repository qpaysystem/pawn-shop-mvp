<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_contracts', function (Blueprint $table) {
            $table->string('lmb_doc_uid', 64)->nullable()->unique()->after('contract_number')->comment('ID документа скупки в 1С (hex)');
            $table->json('lmb_data')->nullable()->after('lmb_doc_uid');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_contracts', function (Blueprint $table) {
            $table->dropColumn(['lmb_doc_uid', 'lmb_data']);
        });
    }
};
