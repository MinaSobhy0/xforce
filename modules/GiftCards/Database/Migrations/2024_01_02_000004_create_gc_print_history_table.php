<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gc_print_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('gift_card_id')->index();
            $table->string('print_format');  // pdf, physical, email
            $table->string('printer_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('printed_by')->nullable();
            $table->timestamps();

            $table->foreign('gift_card_id')
                ->references('id')
                ->on('gift_cards')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'gift_card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gc_print_history');
    }
};
