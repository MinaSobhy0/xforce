<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Scope (hierarchical: tenant → branch → service)
            $table->enum('scope_level', ['tenant', 'branch', 'service'])->default('tenant');
            $table->uuid('branch_id')->nullable();
            $table->uuid('service_id')->nullable();

            // Rule identification
            $table->string('name', 100);
            $table->string('code', 50);
            $table->text('description')->nullable();

            // Rule type
            $table->enum('rule_type', [
                'slot_block',           // Block slots entirely
                'time_restriction',     // Restrict available hours
                'capacity_limit',       // Max appointments per day/practitioner
                'buffer_override',      // Custom buffer time
                'advance_booking',      // Min/max advance booking
                'online_restriction',   // Affect online booking only
                'practitioner_limit'    // Limit specific practitioners
            ]);

            // Conditions (when rule applies) - JSONB
            $table->jsonb('conditions');
            /*
             * {
             *   "days_of_week": [1, 2, 3, 4, 5],      // 0=Sun, 6=Sat
             *   "time_range": {"start": "12:00", "end": "13:00"},
             *   "date_range": {"start": "2024-01-01", "end": "2024-12-31"},
             *   "services": ["uuid1", "uuid2"],
             *   "service_categories": ["laser", "ipl"],
             *   "practitioners": ["uuid1"],
             *   "rooms": ["uuid1"],
             *   "equipment": ["uuid1"],
             *   "patient_type": "new" | "returning"
             * }
             */

            // Actions (what rule does) - JSONB
            $table->jsonb('actions');
            /*
             * For slot_block:       {"block": true, "reason": "Lunch break"}
             * For time_restriction: {"allowed_start": "10:00", "allowed_end": "16:00"}
             * For capacity_limit:   {"max_appointments": 10, "scope": "day" | "practitioner"}
             * For buffer_override:  {"buffer_minutes": 15}
             * For advance_booking:  {"min_hours": 24, "max_days": 30}
             * For online_restriction: {"allow": false, "reason": "Staff booking only"}
             */

            $table->integer('priority')->default(0); // Higher = evaluated first (100 = highest)
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['scope_level', 'branch_id', 'service_id'], 'booking_rules_scope_index');
            $table->index(['tenant_id', 'rule_type']);
            $table->index('priority');
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rules');
    }
};
