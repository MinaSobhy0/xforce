<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove stock input/output account columns
            // These are now handled by system default accounts
            $table->dropColumn(['stock_input_account_id', 'stock_output_account_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('stock_input_account_id')->nullable()->after('image_url');
            $table->foreignId('stock_output_account_id')->nullable()->after('stock_input_account_id');
        });
    }
};
