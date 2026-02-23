<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_type_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('branch_id')->nullable()->index();
            $table->string('type', 50)->index(); // geofence, qr_static, qr_dynamic, biometric
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_type_settings');
    }
};
