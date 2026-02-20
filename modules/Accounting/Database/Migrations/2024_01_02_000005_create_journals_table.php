<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 10)->index();
            $table->jsonb('name');
            $table->string('type', 20)->index(); // sales, purchase, cash, bank, general
            $table->uuid('default_debit_account_id')->nullable();
            $table->uuid('default_credit_account_id')->nullable();
            $table->string('sequence_prefix', 10);
            $table->integer('next_sequence')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
