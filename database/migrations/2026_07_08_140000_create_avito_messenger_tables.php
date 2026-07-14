<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_chats', function (Blueprint $table) {
            $table->id();
            $table->string('branch_slug', 64)->index();
            $table->string('chat_id', 128)->unique();
            $table->string('peer_name', 255)->nullable();
            $table->string('item_id', 64)->nullable()->index();
            $table->string('item_title', 512)->nullable();
            $table->string('item_price', 64)->nullable();
            $table->string('item_url', 1024)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_at')->nullable()->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('avito_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avito_chat_id')->constrained('avito_chats')->cascadeOnDelete();
            $table->string('message_id', 128);
            $table->string('direction', 8)->index(); // in|out
            $table->string('type', 64)->nullable();
            $table->text('text')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->foreignId('call_center_contact_id')->nullable()->constrained('call_center_contacts')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['avito_chat_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_messages');
        Schema::dropIfExists('avito_chats');
    }
};

