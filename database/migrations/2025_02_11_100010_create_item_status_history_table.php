<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** История смены статуса товара. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_status_id')->nullable()->constrained('item_statuses')->nullOnDelete();
            $table->foreignId('new_status_id')->nullable()->constrained('item_statuses')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_status_history');
    }
};
