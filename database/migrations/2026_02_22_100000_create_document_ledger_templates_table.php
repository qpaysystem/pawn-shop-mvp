<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Шаблоны проводок по типам документов: как документ отражается в ОСВ. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_ledger_templates', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 64); // pawn_contract, cash_document, etc.
            $table->string('name', 255)->nullable(); // например "Выдача займа"
            $table->string('debit_account_code', 20);
            $table->string('credit_account_code', 20);
            $table->string('amount_field', 64)->nullable(); // поле документа с суммой: loan_amount, amount, total_amount
            $table->string('comment_template', 255)->nullable(); // шаблон комментария, например "Договор залога №{contract_number}"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_ledger_templates');
    }
};
