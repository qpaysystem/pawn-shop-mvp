<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_center_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 32)->unique();
            $table->string('type', 32);
            $table->string('status', 32)->default('new');
            $table->string('source_channel', 32);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->foreignId('store_id_target')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('call_center_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('preferred_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('lost_reason')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index('source_channel');
        });

        Schema::create('contact_center_lead_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('contact_center_leads')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('expected_price', 12, 2)->nullable();
            $table->decimal('appraised_from', 12, 2)->nullable();
            $table->decimal('appraised_to', 12, 2)->nullable();
            $table->json('photos')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_center_lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('contact_center_leads')->cascadeOnDelete();
            $table->string('channel', 32)->nullable();
            $table->string('event_type', 64);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_center_lead_events');
        Schema::dropIfExists('contact_center_lead_items');
        Schema::dropIfExists('contact_center_leads');
    }
};
