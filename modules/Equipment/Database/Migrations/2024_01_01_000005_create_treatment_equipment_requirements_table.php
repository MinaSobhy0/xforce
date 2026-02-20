<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_equipment_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('treatment_id')->constrained('treatments')->cascadeOnDelete();
            $table->foreignUuid('equipment_type_id')->constrained('equipment_types')->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['treatment_id', 'equipment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_equipment_requirements');
    }
};
