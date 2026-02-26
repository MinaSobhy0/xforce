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
            $table->uuid('service_id')->index();
            $table->integer('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();

            $table->unique(['package_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
