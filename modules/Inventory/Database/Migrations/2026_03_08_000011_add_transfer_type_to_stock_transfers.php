<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stock_transfers', 'transfer_type')) {
            return;
        }

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('transfer_type')->default('internal')->after('status');
            $table->string('reference_type')->nullable()->after('notes');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');

            $table->index(['transfer_type']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex(['transfer_type']);
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->dropColumn(['transfer_type', 'reference_type', 'reference_id']);
        });
    }
};
