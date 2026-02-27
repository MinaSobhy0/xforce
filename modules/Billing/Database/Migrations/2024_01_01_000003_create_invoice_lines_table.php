<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('invoice_id')->index();
            $table->foreignId('service_id')->nullable()->index();
            $table->foreignId('product_id')->nullable()->index();
            $table->foreignId('session_product_id')->nullable();
            $table->foreignId('account_id')->nullable();
            $table->foreignId('treatment_id')->nullable()->index();
            $table->string('line_type', 20)->default('service')->index(); // service, product, package, other
            $table->foreignId('treatment_plan_item_id')->nullable();
            $table->foreignId('appointment_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('unit_price_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->string('discount_type')->nullable();
            $table->jsonb('tax_rates')->nullable();
            $table->integer('tax_minor')->default(0);
            $table->integer('total_minor')->default(0);
            $table->foreignId('package_subscription_id')->nullable();
            $table->foreignId('gift_card_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('session_product_id')->references('id')->on('session_products')->nullOnDelete();
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
