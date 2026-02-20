<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('invoice_id')->index();
            $table->uuid('treatment_id')->nullable()->index();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('unit_price_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->integer('tax_minor')->default(0);
            $table->integer('total_minor')->default(0);
            $table->uuid('package_subscription_id')->nullable();
            $table->uuid('gift_card_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
