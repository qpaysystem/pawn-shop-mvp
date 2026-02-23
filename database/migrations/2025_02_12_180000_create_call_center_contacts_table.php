<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Обращения в компанию: звонки, мессенджеры, соцсети, визиты. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_center_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32); // phone, telegram, whatsapp, vk, visit, other
            $table->string('direction', 16)->default('incoming'); // incoming, outgoing
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('contact_date');
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('outcome', 32)->nullable(); // new, callback, visit_scheduled, converted_pawn, converted_purchase, converted_commission, closed
            $table->foreignId('pawn_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commission_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_center_contacts');
    }
};
