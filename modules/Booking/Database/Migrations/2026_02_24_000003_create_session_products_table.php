<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index(); // No FK - tenants table is in public schema
            $table->foreignId('appointment_id');
            $table->foreignId('product_id');
            $table->foreignId('branch_id')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->integer('unit_price_minor')->default(0); // Sell price per unit
            $table->integer('total_price_minor')->default(0); // quantity * unit_price
            $table->integer('discount_minor')->default(0); // Discount amount
            $table->string('usage_type', 20)->default('applied'); // 'applied' = used on patient, 'sold' = sold to patient
            $table->text('notes')->nullable();
            $table->boolean('is_invoiced')->default(false); // Whether added to invoice
            $table->foreignId('invoice_line_id')->nullable();
            $table->boolean('is_deducted')->default(false); // Whether deducted from inventory
            $table->timestamp('deducted_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'appointment_id']);
            $table->index(['product_id', 'is_deducted']);
            $table->index(['is_invoiced', 'usage_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_products');
    }
};
