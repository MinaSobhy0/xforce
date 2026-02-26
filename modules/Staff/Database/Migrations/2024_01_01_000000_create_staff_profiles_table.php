<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('user_id');
            $table->uuid('branch_id')->nullable();
            $table->string('employee_number', 30)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->jsonb('bio')->nullable();
            $table->jsonb('specializations')->nullable();
            $table->string('commission_type', 20)->default('percentage');
            $table->decimal('commission_percentage', 5, 2)->default(10.00);
            $table->integer('base_salary_minor')->default(0);
            $table->date('hire_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('set null');

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
