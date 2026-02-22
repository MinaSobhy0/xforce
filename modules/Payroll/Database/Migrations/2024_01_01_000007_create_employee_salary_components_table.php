<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('staff_profile_id')->index();
            $table->uuid('salary_rule_id')->nullable()->index();
            $table->string('name'); // Custom name if no salary rule
            $table->string('component_type', 30); // earning, deduction
            $table->string('calculation_type', 30)->default('fixed'); // fixed, percentage, formula
            $table->integer('amount_minor')->default(0);
            $table->decimal('percentage', 8, 4)->nullable();
            $table->text('formula')->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->uuid('loan_id')->nullable(); // For loan repayment deductions
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');

            $table->foreign('salary_rule_id')
                ->references('id')
                ->on('salary_rules')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['tenant_id', 'staff_profile_id', 'is_active']);
            $table->index(['tenant_id', 'component_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
