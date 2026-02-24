<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('category_id')->nullable();
            $table->string('sku', 50)->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->integer('cost_price_minor')->default(0);
            $table->integer('sell_price_minor')->default(0);
            $table->integer('reorder_point')->default(10);
            $table->integer('reorder_quantity')->default(50);
            $table->integer('lead_time_days')->default(7);
            $table->boolean('is_consumable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('barcode', 100)->nullable();
            $table->string('image_url')->nullable();

            // Accounting integration
            $table->uuid('stock_input_account_id')->nullable();
            $table->uuid('stock_output_account_id')->nullable();
            $table->uuid('stock_valuation_account_id')->nullable();
            $table->uuid('income_account_id')->nullable();
            $table->uuid('expense_account_id')->nullable();
            $table->string('valuation_method', 50)->default('average'); // average, fifo, lifo

            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')
                ->on('product_categories')
                ->onDelete('set null');

            $table->foreign('stock_input_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('stock_output_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('stock_valuation_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('income_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('expense_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
