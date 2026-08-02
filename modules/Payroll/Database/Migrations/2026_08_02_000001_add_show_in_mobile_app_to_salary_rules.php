<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors the custom Odoo hr.salary.rule.show_in_mobile_app flag: ONLY
     * rules explicitly flagged true appear in the mobile payslip breakdown.
     * Default false — HR opts rules in from Odoo.
     */
    public function up(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->boolean('show_in_mobile_app')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->dropColumn('show_in_mobile_app');
        });
    }
};
