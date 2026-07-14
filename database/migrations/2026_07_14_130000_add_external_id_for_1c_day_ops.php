<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_documents', 'external_id')) {
                $table->string('external_id', 128)->nullable()->unique()->after('document_number');
            }
            if (! Schema::hasColumn('cash_documents', 'lmb_data')) {
                $table->json('lmb_data')->nullable()->after('comment');
            }
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_statement_lines', 'external_id')) {
                $table->string('external_id', 128)->nullable()->unique()->after('document_number');
            }
        });

        Schema::table('sale_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_contracts', 'external_id')) {
                $table->string('external_id', 128)->nullable()->unique()->after('contract_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_documents', function (Blueprint $table) {
            if (Schema::hasColumn('cash_documents', 'external_id')) {
                $table->dropUnique(['external_id']);
                $table->dropColumn('external_id');
            }
            if (Schema::hasColumn('cash_documents', 'lmb_data')) {
                $table->dropColumn('lmb_data');
            }
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (Schema::hasColumn('bank_statement_lines', 'external_id')) {
                $table->dropUnique(['external_id']);
                $table->dropColumn('external_id');
            }
        });

        Schema::table('sale_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('sale_contracts', 'external_id')) {
                $table->dropUnique(['external_id']);
                $table->dropColumn('external_id');
            }
        });
    }
};
