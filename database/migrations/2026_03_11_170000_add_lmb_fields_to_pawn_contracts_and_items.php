<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pawn_contracts', function (Blueprint $table) {
            $table->string('lmb_doc_uid', 64)->nullable()->unique()->after('contract_number')->comment('ID документа залога в 1С (encode _idrref hex)');
            $table->json('lmb_data')->nullable()->after('lmb_doc_uid');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('lmb_ref', 64)->nullable()->after('barcode')->comment('Ссылка на номенклатуру 1С (hex)');
        });
    }

    public function down(): void
    {
        Schema::table('pawn_contracts', function (Blueprint $table) {
            $table->dropColumn(['lmb_doc_uid', 'lmb_data']);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('lmb_ref');
        });
    }
};
