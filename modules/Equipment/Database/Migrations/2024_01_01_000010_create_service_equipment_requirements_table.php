<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_equipment_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUuid('equipment_type_id')->constrained('equipment_types')->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['service_id', 'equipment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_equipment_requirements');
    }
};
