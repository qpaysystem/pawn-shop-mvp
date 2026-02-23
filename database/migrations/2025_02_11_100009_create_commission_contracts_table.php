<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Договоры комиссии. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 32)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appraiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('commission_percent', 6, 2)->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->decimal('seller_price', 12, 2)->nullable();
            $table->decimal('client_price', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_sold')->default(false);
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('client_paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_contracts');
    }
};
