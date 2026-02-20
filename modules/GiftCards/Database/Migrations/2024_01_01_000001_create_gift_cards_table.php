<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code')->unique();
            $table->uuid('purchaser_patient_id')->nullable()->index();
            $table->uuid('recipient_patient_id')->nullable()->index();
            $table->integer('initial_value_minor');
            $table->integer('remaining_value_minor');
            $table->string('status')->default('draft'); // draft, active, partially_used, fully_used, expired, cancelled
            $table->uuid('purchased_via_invoice_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchaser_patient_id')->references('id')->on('patients')->nullOnDelete();
            $table->foreign('recipient_patient_id')->references('id')->on('patients')->nullOnDelete();
            $table->foreign('purchased_via_invoice_id')->references('id')->on('invoices')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
