<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'lmb_identity_document_type')) {
                $table->string('lmb_identity_document_type', 160)->nullable()->after('passport_data');
            }
            if (! Schema::hasColumn('clients', 'lmb_passport_issued_by')) {
                $table->text('lmb_passport_issued_by')->nullable()->after('lmb_identity_document_type');
            }
            if (! Schema::hasColumn('clients', 'lmb_passport_issued_at')) {
                $table->date('lmb_passport_issued_at')->nullable()->after('lmb_passport_issued_by');
            }
            if (! Schema::hasColumn('clients', 'lmb_registration_address')) {
                $table->text('lmb_registration_address')->nullable()->after('lmb_passport_issued_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            foreach ([
                'lmb_identity_document_type',
                'lmb_passport_issued_by',
                'lmb_passport_issued_at',
                'lmb_registration_address',
            ] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
