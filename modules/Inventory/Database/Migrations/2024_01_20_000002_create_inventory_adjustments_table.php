<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('branch_id')->index();

            $table->string('reference', 30)->unique();
            $table->string('adjustment_type', 30)->default('count');
            // Types: count (physical count), loss, damage, correction, initial

            $table->date('adjustment_date');
            $table->string('status', 20)->default('draft');
            // Status: draft, validated, cancelled

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            // Accounting
            $table->foreignId('journal_entry_id')->nullable();
            $table->integer('total_value_adjustment_minor')->default(0);

            // Approval
            $table->foreignId('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();

            $table->foreignId('created_by')->nullable();
            $table->foreignId('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });

        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('inventory_adjustment_id')->index();
            $table->foreignId('product_id')->index();

            $table->integer('theoretical_qty')->default(0); // System quantity
            $table->integer('counted_qty')->default(0); // Physical count
            $table->integer('difference_qty')->default(0); // counted - theoretical

            // Cost for valuation
            $table->integer('unit_cost_minor')->default(0);
            $table->integer('value_adjustment_minor')->default(0); // difference * unit_cost

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('inventory_adjustment_id')
                ->references('id')
                ->on('inventory_adjustments')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
    }
};
