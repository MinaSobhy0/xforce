<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('journal_id')->nullable()->index();
            $table->string('code')->index();
            $table->date('date')->index();
            $table->string('reference')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('source_type')->nullable()->index();
            $table->uuid('source_id')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->integer('total_debit_minor')->default(0);
            $table->integer('total_credit_minor')->default(0);
            $table->uuid('fiscal_period_id')->nullable()->index();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('reversed_by_id')->nullable();
            $table->uuid('reversal_of_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
