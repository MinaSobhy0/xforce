<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('branch_id');
            $table->string('transfer_number', 30);
            $table->string('status', 20)->default('draft');
            $table->string('transfer_type')->default('internal');
            $table->foreignId('source_location_id');
            $table->foreignId('destination_location_id');
            $table->timestamp('scheduled_date')->nullable();
            $table->timestamp('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('confirmed_by')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();

            $table->foreign('source_location_id')
                ->references('id')
                ->on('stock_locations')
                ->restrictOnDelete();

            $table->foreign('destination_location_id')
                ->references('id')
                ->on('stock_locations')
                ->restrictOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('confirmed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(['tenant_id', 'transfer_number']);
            $table->index(['tenant_id', 'branch_id']);
            $table->index(['status']);
            $table->index(['transfer_type']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('stock_transfer_id');
            $table->foreignId('product_id');
            $table->foreignId('uom_id')->nullable();
            $table->integer('quantity_planned')->default(0);
            $table->integer('quantity_done')->default(0);
            $table->integer('unit_cost_minor')->default(0);
            $table->foreignId('stock_movement_id')->nullable();
            $table->timestamps();

            $table->foreign('stock_transfer_id')
                ->references('id')
                ->on('stock_transfers')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();

            $table->index(['stock_transfer_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
