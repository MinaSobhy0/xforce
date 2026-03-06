<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['service_id']);
        });

        // Make service_id nullable (for products and packages that don't have a service_id)
        DB::statement('ALTER TABLE treatment_plan_items ALTER COLUMN service_id DROP NOT NULL');

        Schema::table('treatment_plan_items', function (Blueprint $table) {
            // Re-add the foreign key with nullOnDelete
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        // Make service_id NOT NULL again
        DB::statement('ALTER TABLE treatment_plan_items ALTER COLUMN service_id SET NOT NULL');

        Schema::table('treatment_plan_items', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }
};
