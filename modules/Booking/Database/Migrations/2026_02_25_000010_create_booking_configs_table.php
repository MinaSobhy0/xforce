<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Note: tenant_id omitted - in schema-per-tenant, data is isolated by schema
            $table->uuid('branch_id')->nullable(); // null = tenant-wide default

            // Step 1: Service & Time Configuration
            $table->integer('slot_duration')->default(30); // minutes
            $table->integer('slot_interval')->nullable(); // null = use slot_duration
            $table->integer('buffer_minutes')->default(5);
            $table->time('working_hours_start')->default('09:00');
            $table->time('working_hours_end')->default('21:00');
            $table->boolean('break_enabled')->default(false);
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();

            // Step 2: Doctor/Practitioner Configuration
            $table->boolean('check_doctor_schedule')->default(true);
            $table->boolean('check_doctor_timeoff')->default(true);
            $table->integer('max_per_doctor_daily')->nullable(); // null = unlimited
            $table->boolean('allow_doctor_overlap')->default(false);

            // Step 3: Room Configuration
            $table->string('room_assignment')->default('service'); // service, auto, manual
            $table->boolean('check_room_availability')->default(true);
            $table->boolean('allow_room_overlap')->default(false);

            // Step 4: Equipment Configuration
            $table->string('equipment_assignment')->default('service'); // service, auto
            $table->boolean('check_equipment_availability')->default(true);
            $table->boolean('allow_equipment_overlap')->default(false);

            // Advance Booking
            $table->integer('min_advance_hours')->default(2);
            $table->integer('max_advance_days')->default(60);
            $table->boolean('allow_same_day')->default(true);
            $table->time('same_day_cutoff')->nullable(); // cutoff time for same-day booking

            $table->timestamps();

            // Index for branch lookup
            $table->index('branch_id');
            // Only one config per branch (null = tenant default)
            $table->unique('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_configs');
    }
};
