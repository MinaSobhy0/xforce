<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Accounting accounts for inventory valuation (like Odoo)
            $table->foreignId('stock_input_account_id')->nullable()->after('image_url');
            $table->foreignId('stock_output_account_id')->nullable()->after('stock_input_account_id');
            $table->foreignId('stock_valuation_account_id')->nullable()->after('stock_output_account_id');

            // Inventory valuation method
            $table->string('valuation_method', 20)->default('standard')->after('stock_valuation_account_id');
            // standard = use cost_price, fifo = first in first out, average = weighted average
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'stock_input_account_id',
                'stock_output_account_id',
                'stock_valuation_account_id',
                'valuation_method',
            ]);
        });
    }
};
