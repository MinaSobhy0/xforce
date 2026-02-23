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
        Schema::table('salary_rules', function (Blueprint $table) {
            // Account fields for journal entries
            $table->uuid('debit_account_id')->nullable()->after('condition');
            $table->uuid('credit_account_id')->nullable()->after('debit_account_id');

            // Whether to create journal entry lines for this rule
            $table->boolean('creates_journal_entry')->default(false)->after('credit_account_id');

            // Optional: account type hints for auto-assignment
            $table->string('default_debit_account_code', 20)->nullable()->after('creates_journal_entry');
            $table->string('default_credit_account_code', 20)->nullable()->after('default_debit_account_code');

            // Foreign keys
            $table->foreign('debit_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->foreign('credit_account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->dropForeign(['debit_account_id']);
            $table->dropForeign(['credit_account_id']);
            $table->dropColumn([
                'debit_account_id',
                'credit_account_id',
                'creates_journal_entry',
                'default_debit_account_code',
                'default_credit_account_code',
            ]);
        });
    }
};
