<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lmb_product_events', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 128)->unique();
            $table->string('event_type', 64)->index();
            $table->string('event_number', 64)->nullable()->index();
            $table->timestamp('event_at')->nullable()->index();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('from_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('to_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('status_name', 64)->nullable();
            $table->foreignId('status_id')->nullable()->constrained('item_statuses')->nullOnDelete();
            $table->string('responsible', 255)->nullable();
            $table->string('executor', 255)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->text('description')->nullable();
            $table->string('source_doc_ref', 255)->nullable();
            $table->boolean('applied')->default(false)->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lmb_product_events');
    }
};
