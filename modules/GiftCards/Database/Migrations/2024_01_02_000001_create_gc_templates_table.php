<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gc_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();

            // Value configuration
            $table->integer('min_amount_minor')->default(10000);  // 100.00
            $table->integer('max_amount_minor')->default(100000000);  // 1,000,000.00
            $table->json('preset_amounts')->nullable();  // [5000, 10000, 25000, 50000]

            // Validity
            $table->integer('validity_days')->default(365);

            // Discount
            $table->string('discount_type')->nullable();  // percentage, fixed, null
            $table->integer('discount_value')->default(0);

            // Card design
            $table->json('card_design')->nullable();  // colors, logo, etc.

            // Behavior
            $table->boolean('allow_partial_redemption')->default(true);
            $table->boolean('requires_activation')->default(true);
            $table->boolean('is_active')->default(true);

            // GL Accounts (FK to chart_of_accounts)
            $table->foreignId('liability_account_id')->nullable();
            $table->foreignId('revenue_account_id')->nullable();
            $table->foreignId('expense_account_id')->nullable();
            $table->foreignId('breakage_account_id')->nullable();
            $table->foreignId('sales_journal_id')->nullable();

            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gc_templates');
    }
};
