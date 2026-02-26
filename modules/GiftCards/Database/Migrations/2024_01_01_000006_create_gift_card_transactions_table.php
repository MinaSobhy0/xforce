<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('gift_card_id')->index();
            $table->string('type'); // activate, redeem, refund, adjust, expire
            $table->integer('amount_minor'); // Can be positive or negative
            $table->integer('running_balance_minor');
            $table->foreignId('invoice_id')->nullable()->index();
            $table->foreignId('payment_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('gift_card_id')->references('id')->on('gift_cards')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();

            $table->index(['tenant_id', 'gift_card_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
    }
};
