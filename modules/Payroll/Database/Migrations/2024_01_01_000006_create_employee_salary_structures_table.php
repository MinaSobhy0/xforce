<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('staff_profile_id')->index();
            $table->foreignId('salary_structure_id')->index();
            $table->integer('base_salary_minor')->default(0);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->foreignId('assigned_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');

            $table->foreign('salary_structure_id')
                ->references('id')
                ->on('salary_structures')
                ->onDelete('cascade');

            $table->foreign('assigned_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['tenant_id', 'staff_profile_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structures');
    }
};
