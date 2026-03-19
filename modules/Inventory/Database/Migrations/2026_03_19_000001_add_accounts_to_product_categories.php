<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('stock_valuation_account_id')->nullable()->after('allow_negative_stock');
            $table->foreignId('stock_input_account_id')->nullable()->after('stock_valuation_account_id');
            $table->foreignId('stock_output_account_id')->nullable()->after('stock_input_account_id');
            $table->foreignId('expense_account_id')->nullable()->after('stock_output_account_id');

            $table->foreign('stock_valuation_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('stock_input_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('stock_output_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('expense_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign(['stock_valuation_account_id']);
            $table->dropForeign(['stock_input_account_id']);
            $table->dropForeign(['stock_output_account_id']);
            $table->dropForeign(['expense_account_id']);

            $table->dropColumn([
                'stock_valuation_account_id',
                'stock_input_account_id',
                'stock_output_account_id',
                'expense_account_id',
            ]);
        });
    }
};
