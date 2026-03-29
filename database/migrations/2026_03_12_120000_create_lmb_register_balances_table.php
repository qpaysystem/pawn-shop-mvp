<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lmb_register_balances', function (Blueprint $table) {
            $table->id();
            $table->string('register_name', 64)->comment('Имя таблицы регистра в 1С (_accumrgNNNN)');
            $table->string('dimension_key', 128)->comment('Ключ измерений (hash или concat hex refs)');
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->json('raw_dimensions')->nullable()->comment('Измерения из 1С (hex refs и т.д.)');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['register_name', 'dimension_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lmb_register_balances');
    }
};
