<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operation_type_id')->constrained('cash_operation_types')->cascadeOnDelete();
            $table->string('document_number', 50)->nullable();
            $table->date('document_date');
            $table->decimal('amount', 14, 2);
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_documents');
    }
};
