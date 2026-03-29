<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * phone_key — последние 10 цифр телефона для сопоставления с 1С при синхронизации (независимо от формата 7/8, скобок и пробелов).
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone_key', 10)->nullable()->after('phone')->index()->comment('Последние 10 цифр телефона для сопоставления с 1С');
        });

        $this->backfillPhoneKey();
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['phone_key']);
            $table->dropColumn('phone_key');
        });
    }

    private function backfillPhoneKey(): void
    {
        $table = 'clients';
        $lastId = 0;
        do {
            $chunk = DB::table($table)->where('id', '>', $lastId)->orderBy('id')->limit(500)->get(['id', 'phone']);
            foreach ($chunk as $row) {
                $digits = preg_replace('/\D/', '', (string) ($row->phone ?? ''));
                $key = strlen($digits) >= 10 ? substr($digits, -10) : null;
                DB::table($table)->where('id', $row->id)->update(['phone_key' => $key]);
            }
            if ($chunk->isEmpty()) {
                break;
            }
            $lastId = $chunk->last()->id;
        } while ($chunk->count() === 500);
    }
};
