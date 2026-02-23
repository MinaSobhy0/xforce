<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Treatment plan reference
            $table->uuid('treatment_plan_id')->nullable()->after('appointment_id');

            // Track deposits (unassigned payments) applied to this invoice
            $table->integer('deposits_applied_minor')->default(0)->after('paid_minor');

            // Index
            $table->index(['tenant_id', 'treatment_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'treatment_plan_id']);
            $table->dropColumn(['treatment_plan_id', 'deposits_applied_minor']);
        });
    }
};
