<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 32)->index();
            $table->unsignedBigInteger('message_id');
            $table->string('chat_type', 16)->default('private')->index();
            $table->string('message_type', 32)->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable()->index();
            $table->string('from_username', 255)->nullable();
            $table->string('from_first_name', 255)->nullable();
            $table->text('text')->nullable();
            $table->text('caption')->nullable();
            $table->string('file_id', 256)->nullable();
            $table->string('file_unique_id', 256)->nullable();
            $table->string('file_name', 512)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamp('message_date')->nullable()->index();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('call_center_contact_id')->nullable()->constrained('call_center_contacts')->nullOnDelete();
            $table->timestamp('portal_pushed_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
