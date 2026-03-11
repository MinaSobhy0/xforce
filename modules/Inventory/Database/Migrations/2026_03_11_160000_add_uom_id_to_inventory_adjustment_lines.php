<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->after('product_id')->constrained('uoms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uom_id');
        });
    }
};
