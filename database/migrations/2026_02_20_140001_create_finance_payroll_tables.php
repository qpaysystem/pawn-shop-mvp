<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Сотрудники (для ФОТ)
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('patronymic')->nullable();
            $table->string('position')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Документы начисления ФОТ
        Schema::create('payroll_accruals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->nullable();
            $table->unsignedSmallInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->date('accrual_date');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Строки начисления по сотрудникам
        Schema::create('payroll_accrual_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_accrual_id')->constrained('payroll_accruals')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_accrual_items');
        Schema::dropIfExists('payroll_accruals');
        Schema::dropIfExists('employees');
    }
};
