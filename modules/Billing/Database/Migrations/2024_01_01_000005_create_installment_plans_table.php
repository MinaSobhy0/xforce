<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('invoice_id')->index();
            $table->integer('total_installments');
            $table->integer('installment_amount_minor');
            $table->string('frequency')->default('monthly');
            $table->date('start_date');
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};
