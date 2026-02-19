<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_branch_pricing', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treatment_id')->index();
            $table->uuid('branch_id')->index();

            $table->integer('price_minor')->comment('Price in minor units (piasters)');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('treatment_id')
                ->references('id')
                ->on('treatments')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('cascade');

            $table->unique(['treatment_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_branch_pricing');
    }
};
