<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('equipment_type_id')->constrained('equipment_types')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->integer('purchase_price_minor')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->integer('total_shots_fired')->default(0);
            $table->string('status')->default('active');
            $table->timestamp('last_maintenance_at')->nullable();
            $table->timestamp('next_maintenance_at')->nullable();
            $table->integer('depreciation_years')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('branch_id');
            $table->index(['status', 'branch_id']);
            $table->index('next_maintenance_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
