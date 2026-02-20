<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('package_id')->index();
            $table->uuid('treatment_id')->index();
            $table->integer('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('treatment_id')->references('id')->on('treatments')->cascadeOnDelete();

            $table->unique(['package_id', 'treatment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
