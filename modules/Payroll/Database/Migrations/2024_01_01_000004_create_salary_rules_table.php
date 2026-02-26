<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('name');
            $table->string('code', 50)->index();
            $table->foreignId('category_id')->index();
            $table->string('amount_type', 30)->default('fixed'); // fixed, percentage, formula
            $table->integer('amount_fixed_minor')->default(0);
            $table->decimal('amount_percentage', 8, 4)->nullable();
            $table->text('amount_formula')->nullable();
            $table->string('condition_type', 30)->nullable(); // always, range, formula
            $table->text('condition_formula')->nullable();
            $table->foreignId('percentage_base_id')->nullable(); // self-reference for percentage calculations
            $table->string('field_mapping')->nullable(); // e.g., contract.salary, employee.allowance
            $table->integer('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('id')
                ->on('salary_rule_categories')
                ->onDelete('cascade');

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'sequence']);
        });

        // Add self-referencing foreign key after table creation
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->foreign('percentage_base_id')
                ->references('id')
                ->on('salary_rules')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_rules');
    }
};
