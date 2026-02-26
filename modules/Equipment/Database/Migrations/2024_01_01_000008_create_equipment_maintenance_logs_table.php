<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('type'); // preventive, corrective, calibration
            $table->text('description')->nullable();
            $table->string('performed_by')->nullable();
            $table->integer('cost_minor')->nullable();
            $table->jsonb('parts_replaced')->nullable();
            $table->date('next_due_date')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index('equipment_id');
            $table->index('type');
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_logs');
    }
};
