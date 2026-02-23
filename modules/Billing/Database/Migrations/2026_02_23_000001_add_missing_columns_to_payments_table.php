<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'patient_id')) {
                $table->foreignUuid('patient_id')->nullable()->after('invoice_id');
            }
            if (!Schema::hasColumn('payments', 'branch_id')) {
                $table->foreignUuid('branch_id')->nullable()->after('patient_id');
            }
            if (!Schema::hasColumn('payments', 'treatment_plan_id')) {
                $table->foreignUuid('treatment_plan_id')->nullable()->after('branch_id');
            }
            if (!Schema::hasColumn('payments', 'appointment_id')) {
                $table->foreignUuid('appointment_id')->nullable()->after('treatment_plan_id');
            }
            if (!Schema::hasColumn('payments', 'status')) {
                $table->string('status')->default('completed')->after('appointment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['patient_id', 'branch_id', 'treatment_plan_id', 'appointment_id', 'status']);
        });
    }
};
