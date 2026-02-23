<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** События «Личный визит клиента»: цель визита и привязка к сделке. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visit_purpose', 64); // appraisal, redemption, non_target, identification
            $table->dateTime('visited_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pawn_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commission_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_visits');
    }
};
