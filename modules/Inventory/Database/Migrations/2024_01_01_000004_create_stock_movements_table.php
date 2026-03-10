<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('product_id');
            $table->foreignId('branch_id');
            $table->string('movement_type', 30);
            $table->foreignId('uom_id')->nullable();
            $table->integer('quantity'); // Positive for in, negative for out
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->integer('unit_cost_minor')->default(0);
            $table->integer('remaining_quantity')->nullable();
            $table->string('reference_type', 50)->nullable(); // appointment, purchase_order, etc.
            $table->foreignId('reference_id')->nullable();
            $table->foreignId('source_branch_id')->nullable(); // For transfers
            $table->foreignId('destination_branch_id')->nullable(); // For transfers
            $table->foreignId('source_location_id')->nullable();
            $table->foreignId('destination_location_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            $table->foreign('source_branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('set null');

            $table->foreign('destination_branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['tenant_id', 'product_id', 'created_at']);
            $table->index(['tenant_id', 'branch_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
