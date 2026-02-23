<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Договоры залога. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pawn_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 32)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appraiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('loan_amount', 12, 2);
            $table->decimal('loan_percent', 6, 2)->nullable();
            $table->date('loan_date');
            $table->date('expiry_date');
            $table->decimal('buyback_amount', 12, 2)->nullable();
            $table->boolean('is_redeemed')->default(false);
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pawn_contracts');
    }
};
