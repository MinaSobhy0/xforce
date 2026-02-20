<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('journal_entry_id')->index();
            $table->uuid('account_id')->index();
            $table->integer('debit_minor')->default(0);
            $table->integer('credit_minor')->default(0);
            $table->text('description')->nullable();
            $table->uuid('branch_id')->nullable()->index();
            $table->string('partner_type')->nullable();
            $table->uuid('partner_id')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('chart_of_accounts');
            $table->index(['tenant_id', 'account_id']);
            $table->index(['partner_type', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
