<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code')->index();
            $table->uuid('invoice_id')->index();
            $table->uuid('journal_id')->nullable()->index(); // Payment method journal
            $table->integer('amount_minor');
            $table->string('reference_number')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->uuid('gift_card_id')->nullable();
            $table->uuid('received_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->index();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->index(['tenant_id', 'journal_id']);
            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
