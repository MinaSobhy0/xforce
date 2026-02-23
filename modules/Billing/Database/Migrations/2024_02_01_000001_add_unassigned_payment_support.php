<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Make invoice_id nullable to support unassigned payments (deposits)
            $table->uuid('invoice_id')->nullable()->change();

            // Add patient for unassigned payments
            $table->uuid('patient_id')->nullable()->after('invoice_id');

            // Add branch for unassigned payments
            $table->uuid('branch_id')->nullable()->after('patient_id');

            // Add treatment plan reference
            $table->uuid('treatment_plan_id')->nullable()->after('branch_id');

            // Add appointment reference (deposit collected at booking)
            $table->uuid('appointment_id')->nullable()->after('treatment_plan_id');

            // Status for tracking payment state
            $table->string('status')->default('completed')->after('appointment_id');

            // Indexes
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'treatment_plan_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Drop the foreign key constraint that requires invoice_id
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'patient_id']);
            $table->dropIndex(['tenant_id', 'treatment_plan_id']);
            $table->dropIndex(['tenant_id', 'status']);

            $table->dropColumn([
                'patient_id',
                'branch_id',
                'treatment_plan_id',
                'appointment_id',
                'status',
            ]);

            $table->uuid('invoice_id')->nullable(false)->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }
};
