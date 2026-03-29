<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('metal', 32)->nullable()->after('description');
            $table->string('sample', 16)->nullable()->after('metal');
            $table->decimal('weight_grams', 10, 3)->nullable()->after('sample');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['metal', 'sample', 'weight_grams']);
        });
    }
};
