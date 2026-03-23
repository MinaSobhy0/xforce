<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove stock input/output account columns
        // These are now handled by system default accounts
        $columnsToDrop = [];
        if (Schema::hasColumn('products', 'stock_input_account_id')) {
            $columnsToDrop[] = 'stock_input_account_id';
        }
        if (Schema::hasColumn('products', 'stock_output_account_id')) {
            $columnsToDrop[] = 'stock_output_account_id';
        }
        if (!empty($columnsToDrop)) {
            Schema::table('products', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('products', 'stock_input_account_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('stock_input_account_id')->nullable()->after('image_url');
            });
        }
        if (!Schema::hasColumn('products', 'stock_output_account_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('stock_output_account_id')->nullable()->after('stock_input_account_id');
            });
        }
    }
};
