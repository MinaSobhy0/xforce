<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->dropColumn('journal_entry_id');
        });
    }
};
