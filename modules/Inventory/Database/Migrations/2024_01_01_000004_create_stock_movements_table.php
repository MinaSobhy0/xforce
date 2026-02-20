<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id');
            $table->uuid('branch_id');
            $table->string('movement_type', 30);
            $table->integer('quantity'); // Positive for in, negative for out
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reference_type', 50)->nullable(); // appointment, purchase_order, etc.
            $table->uuid('reference_id')->nullable();
            $table->uuid('source_branch_id')->nullable(); // For transfers
            $table->uuid('destination_branch_id')->nullable(); // For transfers
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
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
