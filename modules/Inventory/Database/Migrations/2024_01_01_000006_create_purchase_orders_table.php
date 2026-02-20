<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('branch_id');
            $table->uuid('supplier_id');
            $table->string('order_number', 30)->unique();
            $table->string('status', 30)->default('draft');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->integer('subtotal_minor')->default(0);
            $table->integer('tax_amount_minor')->default(0);
            $table->integer('discount_amount_minor')->default(0);
            $table->integer('shipping_amount_minor')->default(0);
            $table->integer('total_amount_minor')->default(0);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('received_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
