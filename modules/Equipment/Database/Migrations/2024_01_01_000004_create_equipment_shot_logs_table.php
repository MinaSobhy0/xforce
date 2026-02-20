<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_shot_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->uuid('appointment_id')->nullable(); // FK added later when Booking module exists
            $table->integer('shots_count');
            $table->string('energy_setting')->nullable();
            $table->string('spot_size')->nullable();
            $table->string('pulse_duration')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index('equipment_id');
            $table->index('appointment_id');
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_shot_logs');
    }
};
