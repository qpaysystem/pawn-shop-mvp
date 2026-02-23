<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Статусы товара (Принят в ломбард, На витрине, Продан и т.д.). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 32)->nullable(); // для бейджа в интерфейсе
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_statuses');
    }
};
