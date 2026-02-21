<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_rule_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('code', 50)->index();
            $table->text('description')->nullable();
            $table->string('type', 30); // earning, deduction, allowance, benefit, gross, net
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_rule_categories');
    }
};
