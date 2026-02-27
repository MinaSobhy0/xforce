<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('code')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->string('vendor_reference')->nullable();
            $table->string('status')->default('draft');
            $table->integer('subtotal_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->string('discount_type')->default('fixed');
            $table->integer('tax_minor')->default(0);
            $table->integer('total_minor')->default(0);
            $table->integer('paid_minor')->default(0);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('validated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('supplier_id');
            $table->index('status');
        });

        Schema::create('vendor_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('vendor_bill_id')->constrained('vendor_bills')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('account_id')->nullable();
            $table->foreignId('purchase_order_line_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('unit_price_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->string('discount_type')->default('fixed');
            $table->json('tax_rates')->nullable(); // Array of tax rate percentages
            $table->integer('tax_minor')->default(0);
            $table->integer('total_minor')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('vendor_bill_id');
        });

        // Add vendor_bill_id to purchase_orders if not exists
        if (!Schema::hasColumn('purchase_orders', 'vendor_bill_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('vendor_bill_id')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('vendor_bill_id');
        });
        Schema::dropIfExists('vendor_bill_lines');
        Schema::dropIfExists('vendor_bills');
    }
};
