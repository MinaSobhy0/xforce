<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            // Treatment plan item reference for tracking
            $table->uuid('treatment_plan_item_id')->nullable()->after('service_id');

            // Appointment reference
            $table->uuid('appointment_id')->nullable()->after('treatment_plan_item_id');

            // Indexes
            $table->index(['tenant_id', 'treatment_plan_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'treatment_plan_item_id']);

            $table->dropColumn([
                'treatment_plan_item_id',
                'appointment_id',
            ]);
        });
    }
};
