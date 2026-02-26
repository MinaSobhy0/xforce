<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('asset_id');

            // Period information
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_label', 20); // e.g., "2024-01"

            // Depreciation amounts (all in minor/cents)
            $table->integer('depreciation_amount_minor'); // This period's depreciation
            $table->integer('accumulated_depreciation_minor'); // Running total after this entry
            $table->integer('book_value_minor'); // Book value after this entry

            // Journal entry reference
            $table->foreignId('journal_entry_id')->nullable();

            // Status
            $table->string('status', 50)->default('draft');
            // Values: draft, posted, reversed

            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'period_label']);
            $table->index(['tenant_id', 'period_label']);
            $table->index(['tenant_id', 'status']);

            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_entries');
    }
};
