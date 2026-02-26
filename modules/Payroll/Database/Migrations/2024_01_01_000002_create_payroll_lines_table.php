<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('payroll_run_id');
            $table->foreignId('staff_profile_id');
            $table->integer('base_salary_minor')->default(0);
            $table->integer('commissions_minor')->default(0);
            $table->integer('bonuses_minor')->default(0);
            $table->integer('deductions_minor')->default(0);
            $table->integer('tax_minor')->default(0);
            $table->integer('social_insurance_minor')->default(0);
            $table->integer('net_salary_minor')->default(0);
            $table->jsonb('commission_records_json')->nullable();
            $table->jsonb('bonus_details_json')->nullable();
            $table->jsonb('deduction_details_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys will be resolved at application level
            // since tables may be in different schemas

            $table->unique(['payroll_run_id', 'staff_profile_id']);
            $table->index(['tenant_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
    }
};
