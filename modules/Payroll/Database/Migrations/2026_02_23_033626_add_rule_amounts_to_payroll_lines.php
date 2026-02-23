<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            // Store rule amounts for journal entry creation
            // Format: [{ rule_id, rule_code, rule_name, category_type, amount_minor }]
            $table->jsonb('rule_amounts_json')->nullable()->after('deduction_details_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn('rule_amounts_json');
        });
    }
};
