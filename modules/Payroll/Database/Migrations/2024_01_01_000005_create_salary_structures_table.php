<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('name');
            $table->string('code', 50)->index();
            $table->text('description')->nullable();
            $table->string('pay_frequency', 30)->default('monthly'); // monthly, bi-weekly, weekly, daily, hourly
            $table->string('currency', 10)->default('EGP');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Pivot table for salary structure rules
        Schema::create('salary_structure_rules', function (Blueprint $table) {
            $table->foreignId('salary_structure_id');
            $table->foreignId('salary_rule_id');
            $table->integer('sequence')->default(0);
            $table->timestamps();

            $table->primary(['salary_structure_id', 'salary_rule_id']);

            $table->foreign('salary_structure_id')
                ->references('id')
                ->on('salary_structures')
                ->onDelete('cascade');

            $table->foreign('salary_rule_id')
                ->references('id')
                ->on('salary_rules')
                ->onDelete('cascade');

            $table->index(['salary_structure_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structure_rules');
        Schema::dropIfExists('salary_structures');
    }
};
