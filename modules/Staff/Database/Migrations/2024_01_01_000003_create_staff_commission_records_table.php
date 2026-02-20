<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_commission_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('staff_profile_id');
            $table->uuid('appointment_id')->nullable();
            $table->uuid('payroll_line_id')->nullable();
            $table->integer('amount_minor');
            $table->integer('revenue_minor');
            $table->string('commission_type', 20);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('staff_profile_id')
                ->references('id')
                ->on('staff_profiles')
                ->onDelete('cascade');

            // Foreign key to appointments will be added if table exists
            // $table->foreign('appointment_id')
            //     ->references('id')
            //     ->on('appointments')
            //     ->onDelete('set null');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'staff_profile_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_commission_records');
    }
};
