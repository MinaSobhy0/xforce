<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_consumables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('appointment_id');
            $table->foreignId('product_id');
            $table->foreignId('branch_id')->nullable(); // Branch from which stock is deducted
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->integer('unit_cost_minor')->default(0); // Cost per unit at time of use
            $table->integer('total_cost_minor')->default(0); // quantity * unit_cost
            $table->text('notes')->nullable();
            $table->boolean('is_deducted')->default(false); // Whether deducted from inventory
            $table->timestamp('deducted_at')->nullable();
            $table->foreignId('deducted_by')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'appointment_id']);
            $table->index(['product_id', 'is_deducted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_consumables');
    }
};
