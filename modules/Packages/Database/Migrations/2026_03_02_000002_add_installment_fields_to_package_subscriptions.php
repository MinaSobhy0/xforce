<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_subscriptions', function (Blueprint $table) {
            // Pricing and payment tracking
            $table->integer('package_price_minor')->default(0)->after('invoice_id');
            $table->integer('deposit_paid_minor')->default(0)->after('package_price_minor');
            $table->integer('balance_remaining_minor')->default(0)->after('deposit_paid_minor');

            // Activation rules
            $table->string('activation_rule', 20)->default('immediate')->after('balance_remaining_minor');

            // Revenue recognition tracking
            $table->integer('recognized_revenue_minor')->default(0)->after('activation_rule');
            $table->integer('unrecognized_revenue_minor')->default(0)->after('recognized_revenue_minor');

            // Link to deferred revenue account for this subscription
            $table->foreignId('unearned_revenue_account_id')->nullable()->after('unrecognized_revenue_minor');

            $table->foreign('unearned_revenue_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            // Branch for multi-branch tracking
            $table->foreignId('branch_id')->nullable()->after('unearned_revenue_account_id');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->nullOnDelete();

            // Created by user
            $table->foreignId('created_by_user_id')->nullable()->after('branch_id');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('package_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['unearned_revenue_account_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['created_by_user_id']);

            $table->dropColumn([
                'package_price_minor',
                'deposit_paid_minor',
                'balance_remaining_minor',
                'activation_rule',
                'recognized_revenue_minor',
                'unrecognized_revenue_minor',
                'unearned_revenue_account_id',
                'branch_id',
                'created_by_user_id',
            ]);
        });
    }
};
