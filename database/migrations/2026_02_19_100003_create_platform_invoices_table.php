<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('number', 50)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('plan_code', 50)->nullable();
            $table->integer('plan_charge_minor')->default(0);
            $table->integer('addon_charges_minor')->default(0);
            $table->integer('overage_charges_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->string('discount_code', 50)->nullable();
            $table->integer('subtotal_minor')->default(0);
            $table->integer('tax_minor')->default(0);
            $table->decimal('tax_rate', 5, 4)->default(0.14); // 14% Egyptian VAT
            $table->integer('total_minor')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->string('status', 20)->default('pending'); // draft, pending, paid, overdue, refunded, cancelled
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('line_items')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index(['period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
    }
};
