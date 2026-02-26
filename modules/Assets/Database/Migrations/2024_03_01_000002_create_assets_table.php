<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 50)->index();
            $table->jsonb('name'); // Translatable

            // Relationships
            $table->uuid('asset_type_id');
            $table->uuid('branch_id')->nullable();

            // Acquisition details
            $table->date('acquisition_date');
            $table->integer('acquisition_cost_minor'); // Original cost in cents
            $table->string('acquisition_method', 50)->default('purchase');
            // Values: purchase, transfer, donation, found

            // Source references (from purchase order)
            $table->uuid('purchase_order_id')->nullable();
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('product_id')->nullable();

            // Depreciation values (all in minor/cents)
            $table->integer('salvage_value_minor')->default(0); // Calculated from type %
            $table->integer('depreciable_value_minor')->default(0); // Cost - salvage
            $table->integer('accumulated_depreciation_minor')->default(0); // Total depreciation to date
            $table->integer('book_value_minor')->default(0); // Cost - accumulated

            // Depreciation dates
            $table->date('depreciation_start_date')->nullable();
            $table->date('last_depreciation_date')->nullable();

            // Status
            $table->string('status', 50)->default('draft');
            // Values: draft, active, fully_depreciated, disposed, written_off

            // Disposal details
            $table->date('disposal_date')->nullable();
            $table->integer('disposal_value_minor')->nullable(); // Sale proceeds
            $table->string('disposal_method', 50)->nullable();
            // Values: sale, scrap, donation, theft, damage
            $table->text('disposal_notes')->nullable();
            $table->uuid('disposal_journal_entry_id')->nullable();

            // Additional info
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->uuid('assigned_to_user_id')->nullable();
            $table->text('notes')->nullable();

            // Journal entry reference for acquisition
            $table->uuid('acquisition_journal_entry_id')->nullable();

            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'asset_type_id']);
            $table->index(['tenant_id', 'branch_id']);

            $table->foreign('asset_type_id')->references('id')->on('asset_types')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
