<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('patronymic');
            $table->text('passport_data')->nullable()->after('phone');
            $table->string('photo_path')->nullable()->after('passport_data');
            $table->text('registration_address')->nullable()->after('photo_path');
            $table->foreignId('user_id')->nullable()->after('store_id')->constrained('users')->nullOnDelete();
            $table->string('telegram', 100)->nullable()->after('user_id');
            $table->text('character_description')->nullable()->after('telegram');
            $table->text('professional_data')->nullable()->after('character_description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram', 100)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'phone',
                'passport_data',
                'photo_path',
                'registration_address',
                'telegram',
                'character_description',
                'professional_data',
            ]);
        });
    }
};
