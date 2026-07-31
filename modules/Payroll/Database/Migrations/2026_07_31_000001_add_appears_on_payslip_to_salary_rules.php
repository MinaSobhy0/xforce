<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors Odoo's hr.salary.rule.appears_on_payslip: rules flagged false
     * (employer contributions, intermediate computation rules, ...) are used
     * in calculations but hidden from the employee-facing payslip breakdown.
     */
    public function up(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->boolean('appears_on_payslip')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->dropColumn('appears_on_payslip');
        });
    }
};
