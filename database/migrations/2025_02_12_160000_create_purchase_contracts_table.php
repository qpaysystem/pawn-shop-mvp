<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Договоры скупки (выкуп товара у клиента). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 32)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appraiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('purchase_amount', 12, 2);
            $table->date('purchase_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_contracts');
    }
};
