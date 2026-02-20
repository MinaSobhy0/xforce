<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('installment_plan_id')->index();
            $table->integer('installment_number');
            $table->integer('amount_minor');
            $table->date('due_date')->index();
            $table->timestamp('paid_at')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->foreign('installment_plan_id')->references('id')->on('installment_plans')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->index(['tenant_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_schedules');
    }
};
