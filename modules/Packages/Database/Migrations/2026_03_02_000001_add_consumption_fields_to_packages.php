<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add consumption type to packages
        Schema::table('packages', function (Blueprint $table) {
            $table->string('consumption_type', 20)->default('sessions')->after('type');
            $table->integer('min_deposit_percent')->default(0)->after('validity_days');
        });

        // Add unit type override to package items
        Schema::table('package_items', function (Blueprint $table) {
            $table->string('unit_type', 20)->nullable()->after('quantity');
            $table->integer('pulses_per_session')->nullable()->after('unit_type');
        });

        // Add consumption tracking fields to package session usages
        Schema::table('package_session_usages', function (Blueprint $table) {
            $table->integer('quantity_used')->default(1)->after('used_at');
            $table->string('unit_type', 20)->default('session')->after('quantity_used');
            $table->foreignId('journal_entry_id')->nullable()->after('notes');

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('package_session_usages', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn(['quantity_used', 'unit_type', 'journal_entry_id']);
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->dropColumn(['unit_type', 'pulses_per_session']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['consumption_type', 'min_deposit_percent']);
        });
    }
};
