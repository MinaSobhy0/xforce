<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['practitioner_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            // Make the column nullable
            $table->foreignId('practitioner_id')->nullable()->change();

            // Re-add the foreign key with nullOnDelete
            $table->foreign('practitioner_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Note: Reverting this requires all appointments to have a practitioner_id
        // This is a destructive operation if any appointments have null practitioner_id
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['practitioner_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('practitioner_id')->nullable(false)->change();

            $table->foreign('practitioner_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
