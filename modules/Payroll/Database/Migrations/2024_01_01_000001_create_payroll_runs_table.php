<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('run_number', 30)->unique();
            $table->integer('period_year');
            $table->integer('period_month');
            $table->string('status', 20)->default('draft');
            $table->integer('total_base_salary_minor')->default(0);
            $table->integer('total_commissions_minor')->default(0);
            $table->integer('total_bonuses_minor')->default(0);
            $table->integer('total_deductions_minor')->default(0);
            $table->integer('total_net_salary_minor')->default(0);
            $table->integer('employee_count')->default(0);
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('paid_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['tenant_id', 'period_year', 'period_month']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
