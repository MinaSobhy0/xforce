<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // Add uom_id to session_products
        Schema::table('session_products', function (Blueprint $table) {
            if (!Schema::hasColumn('session_products', 'uom_id')) {
                $table->foreignId('uom_id')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('session_products', 'session_data_id')) {
                $table->foreignId('session_data_id')->nullable()->after('appointment_id');
            }
            if (!Schema::hasColumn('session_products', 'visit_id')) {
                $table->foreignId('visit_id')->nullable()->after('session_data_id');
            }
            if (!Schema::hasColumn('session_products', 'discount_type')) {
                $table->string('discount_type', 20)->default('none')->after('total_price_minor');
            }
            if (!Schema::hasColumn('session_products', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            }
        });

        // Add uom_id FK constraint if column exists and FK doesn't
        try {
            Schema::table('session_products', function (Blueprint $table) {
                $table->foreign('uom_id')->references('id')->on('uoms')->nullOnDelete();
            });
        } catch (\Exception $e) {
            // FK may already exist
        }

        // Add uom_id to session_consumables
        Schema::table('session_consumables', function (Blueprint $table) {
            if (!Schema::hasColumn('session_consumables', 'uom_id')) {
                $table->foreignId('uom_id')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('session_consumables', 'base_quantity')) {
                $table->decimal('base_quantity', 10, 2)->nullable()->after('quantity');
            }
        });

        // Add uom_id FK constraint if column exists and FK doesn't
        try {
            Schema::table('session_consumables', function (Blueprint $table) {
                $table->foreign('uom_id')->references('id')->on('uoms')->nullOnDelete();
            });
        } catch (\Exception $e) {
            // FK may already exist
        }
    }

    public function down(): void
    {
        Schema::table('session_products', function (Blueprint $table) {
            if (Schema::hasColumn('session_products', 'uom_id')) {
                $table->dropForeign(['uom_id']);
                $table->dropColumn('uom_id');
            }
            if (Schema::hasColumn('session_products', 'session_data_id')) {
                $table->dropColumn('session_data_id');
            }
            if (Schema::hasColumn('session_products', 'visit_id')) {
                $table->dropColumn('visit_id');
            }
            if (Schema::hasColumn('session_products', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
            if (Schema::hasColumn('session_products', 'discount_value')) {
                $table->dropColumn('discount_value');
            }
        });

        Schema::table('session_consumables', function (Blueprint $table) {
            if (Schema::hasColumn('session_consumables', 'uom_id')) {
                $table->dropForeign(['uom_id']);
                $table->dropColumn('uom_id');
            }
            if (Schema::hasColumn('session_consumables', 'base_quantity')) {
                $table->dropColumn('base_quantity');
            }
        });
    }
};
