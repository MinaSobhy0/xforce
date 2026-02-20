<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code')->index();
            $table->uuid('patient_id')->index();
            $table->uuid('branch_id')->index();
            $table->uuid('appointment_id')->nullable()->index();
            $table->string('type')->default('standard');
            $table->string('status')->default('draft')->index();
            $table->integer('subtotal_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->string('discount_type')->nullable();
            $table->integer('tax_minor')->default(0);
            $table->integer('total_minor')->default(0);
            $table->integer('paid_minor')->default(0);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
