<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_products', function (Blueprint $table) {
            $table->foreignId('stock_movement_id')
                ->nullable()
                ->after('deducted_at')
                ->constrained('stock_movements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_movement_id');
        });
    }
};
