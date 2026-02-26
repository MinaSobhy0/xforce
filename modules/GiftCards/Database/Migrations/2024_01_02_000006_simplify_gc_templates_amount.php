<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gc_templates', function (Blueprint $table) {
            // Rename min_amount_minor to amount_minor
            $table->renameColumn('min_amount_minor', 'amount_minor');
        });

        Schema::table('gc_templates', function (Blueprint $table) {
            // Drop columns that are no longer needed
            $table->dropColumn(['max_amount_minor', 'preset_amounts', 'revenue_account_id', 'breakage_account_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gc_templates', function (Blueprint $table) {
            $table->renameColumn('amount_minor', 'min_amount_minor');
        });

        Schema::table('gc_templates', function (Blueprint $table) {
            $table->integer('max_amount_minor')->default(100000000)->after('min_amount_minor');
            $table->json('preset_amounts')->nullable()->after('max_amount_minor');
            $table->foreignId('revenue_account_id')->nullable()->after('liability_account_id');
            $table->foreignId('breakage_account_id')->nullable()->after('expense_account_id');
        });
    }
};
