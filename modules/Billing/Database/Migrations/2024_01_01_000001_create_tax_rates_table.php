<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->jsonb('name');
            $table->decimal('rate', 5, 2)->default(0);
            $table->string('type')->default('sales'); // sales, purchase
            $table->foreignId('account_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('chart_of_accounts')
                ->nullOnDelete();

            $table->index(['tenant_id', 'is_default']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
