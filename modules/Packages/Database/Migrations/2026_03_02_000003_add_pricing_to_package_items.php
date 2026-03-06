<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            // Consumption type per service line (sessions or pulses)
            $table->string('consumption_type', 20)->default('sessions')->after('quantity');

            // Pricing per service line
            $table->integer('unit_price_minor')->default(0)->after('consumption_type');

            // Discount percentage for this item (optional)
            $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_price_minor');
        });

        // Remove the old unit_type column if it exists (replaced by consumption_type)
        if (Schema::hasColumn('package_items', 'unit_type')) {
            Schema::table('package_items', function (Blueprint $table) {
                $table->dropColumn('unit_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            $table->dropColumn(['consumption_type', 'unit_price_minor', 'discount_percent']);

            // Restore unit_type
            $table->string('unit_type', 20)->nullable()->after('quantity');
        });
    }
};
