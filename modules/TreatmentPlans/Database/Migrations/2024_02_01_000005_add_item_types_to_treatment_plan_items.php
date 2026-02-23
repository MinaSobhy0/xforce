<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            // Item type: service, package, product
            $table->string('item_type')->default('service')->after('treatment_plan_id');

            // Polymorphic columns for itemable (Service, Package, Product)
            $table->string('itemable_type')->nullable()->after('item_type');
            $table->uuid('itemable_id')->nullable()->after('itemable_type');

            // Pricing
            $table->integer('unit_price_minor')->default(0)->after('session_interval_days');
            $table->integer('discount_minor')->default(0)->after('unit_price_minor');
            $table->integer('total_minor')->default(0)->after('discount_minor');

            // Quantity tracking (replacing recommended_sessions for non-service items)
            $table->integer('quantity')->default(1)->after('total_minor');
            $table->integer('completed_quantity')->default(0)->after('quantity');
            $table->integer('invoiced_quantity')->default(0)->after('completed_quantity');

            // For products
            $table->boolean('is_delivered')->default(false)->after('invoiced_quantity');
            $table->timestamp('delivered_at')->nullable()->after('is_delivered');

            // Index for polymorphic lookup
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropIndex(['itemable_type', 'itemable_id']);
            $table->dropColumn([
                'item_type',
                'itemable_type',
                'itemable_id',
                'unit_price_minor',
                'discount_minor',
                'total_minor',
                'quantity',
                'completed_quantity',
                'invoiced_quantity',
                'is_delivered',
                'delivered_at',
            ]);
        });
    }
};
