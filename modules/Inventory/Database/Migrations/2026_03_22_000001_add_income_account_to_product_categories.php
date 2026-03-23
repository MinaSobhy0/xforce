<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = $this->getConnection() ?: config('database.default');

        if (!Schema::connection($connection)->hasColumn('product_categories', 'income_account_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->foreignId('income_account_id')->nullable()->after('expense_account_id');

                $table->foreign('income_account_id')
                    ->references('id')
                    ->on('chart_of_accounts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $connection = $this->getConnection() ?: config('database.default');

        if (Schema::connection($connection)->hasColumn('product_categories', 'income_account_id')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropForeign(['income_account_id']);
                $table->dropColumn('income_account_id');
            });
        }
    }
};
