<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'income_account_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->uuid('income_account_id')->nullable()->after('stock_valuation_account_id');
                $table->uuid('expense_account_id')->nullable()->after('income_account_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['income_account_id', 'expense_account_id']);
        });
    }
};
