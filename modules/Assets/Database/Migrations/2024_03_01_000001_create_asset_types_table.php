<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 50)->index();
            $table->jsonb('name'); // Translatable
            $table->jsonb('description')->nullable(); // Translatable

            // Depreciation configuration
            $table->string('depreciation_method', 50)->default('straight_line');
            // Values: straight_line, declining_balance, sum_of_years, no_depreciation
            $table->integer('useful_life_years')->default(5);
            $table->decimal('salvage_value_percent', 5, 2)->default(0); // Residual value %
            $table->decimal('declining_balance_rate', 5, 2)->nullable(); // For declining balance method

            // GL Accounts (FK to chart_of_accounts)
            $table->uuid('fixed_asset_account_id')->nullable(); // DR on acquisition
            $table->uuid('accumulated_depreciation_account_id')->nullable(); // CR on depreciation
            $table->uuid('depreciation_expense_account_id')->nullable(); // DR on depreciation
            $table->uuid('gain_loss_account_id')->nullable(); // For disposal gain/loss

            // Behavior
            $table->boolean('auto_create_on_purchase')->default(true);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_types');
    }
};
