<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('branch_id');
            $table->foreignId('parent_id')->nullable();
            $table->string('code', 30);
            $table->jsonb('name'); // Translatable
            $table->string('location_type', 20)->default('internal');
            $table->string('parent_path', 255)->nullable(); // Materialized path
            $table->integer('level')->default(0);
            $table->boolean('is_scrap_location')->default(false);
            $table->boolean('is_return_location')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->restrictOnDelete();

            $table->foreign('parent_id')
                ->references('id')
                ->on('stock_locations')
                ->nullOnDelete();

            // Unique code per branch
            $table->unique(['branch_id', 'code']);

            $table->index(['tenant_id', 'branch_id']);
            $table->index(['branch_id', 'location_type']);
            $table->index('parent_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
