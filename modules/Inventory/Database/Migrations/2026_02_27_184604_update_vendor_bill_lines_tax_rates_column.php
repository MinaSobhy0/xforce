<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if old tax_rate column exists and new tax_rates doesn't
        if (Schema::hasColumn('vendor_bill_lines', 'tax_rate') && !Schema::hasColumn('vendor_bill_lines', 'tax_rates')) {
            // Add new tax_rates JSON column
            Schema::table('vendor_bill_lines', function (Blueprint $table) {
                $table->json('tax_rates')->nullable()->after('discount_type');
            });

            // Migrate data: convert tax_rate decimal to tax_rates JSON array
            DB::table('vendor_bill_lines')
                ->whereNotNull('tax_rate')
                ->where('tax_rate', '!=', 0)
                ->eachById(function ($line) {
                    DB::table('vendor_bill_lines')
                        ->where('id', $line->id)
                        ->update(['tax_rates' => json_encode([(string) $line->tax_rate])]);
                });

            // Drop old tax_rate column
            Schema::table('vendor_bill_lines', function (Blueprint $table) {
                $table->dropColumn('tax_rate');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('vendor_bill_lines', 'tax_rates') && !Schema::hasColumn('vendor_bill_lines', 'tax_rate')) {
            // Add back tax_rate column
            Schema::table('vendor_bill_lines', function (Blueprint $table) {
                $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_type');
            });

            // Migrate data back: take first rate from array
            DB::table('vendor_bill_lines')
                ->whereNotNull('tax_rates')
                ->eachById(function ($line) {
                    $rates = json_decode($line->tax_rates, true) ?? [];
                    $firstRate = !empty($rates) ? (float) $rates[0] : 0;
                    DB::table('vendor_bill_lines')
                        ->where('id', $line->id)
                        ->update(['tax_rate' => $firstRate]);
                });

            // Drop tax_rates column
            Schema::table('vendor_bill_lines', function (Blueprint $table) {
                $table->dropColumn('tax_rates');
            });
        }
    }
};
