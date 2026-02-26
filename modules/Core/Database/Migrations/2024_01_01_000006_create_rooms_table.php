<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('branch_id')->index();

            $table->jsonb('name'); // Translatable
            $table->string('code', 20)->nullable()->index();
            $table->jsonb('description')->nullable(); // Translatable
            $table->integer('capacity')->default(1);
            $table->string('floor', 20)->nullable();
            $table->enum('room_type', ['treatment', 'consultation', 'waiting', 'reception', 'storage', 'staff', 'other'])->default('treatment');
            $table->string('color', 7)->nullable(); // Hex color for calendar display
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable')->default(true);
            $table->jsonb('settings')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // No FK to tenants - schema isolation handles tenant context
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');

            $table->index(['tenant_id', 'branch_id', 'is_active']);
            $table->index(['branch_id', 'room_type', 'is_bookable']);
            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
