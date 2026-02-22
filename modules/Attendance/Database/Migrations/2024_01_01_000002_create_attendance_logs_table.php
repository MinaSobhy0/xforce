<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('attendance_id')->index();
            $table->string('type', 30); // check_in, check_out, break_start, break_end
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('altitude', 10, 2)->nullable();
            $table->decimal('horizontal_accuracy', 8, 2)->nullable();
            $table->decimal('vertical_accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->text('address')->nullable();
            $table->string('source', 30)->default('manual'); // manual, mobile, biometric, web
            $table->json('device_info')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('attendance_id')
                ->references('id')
                ->on('attendances')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->index(['tenant_id', 'type']);
            $table->index(['attendance_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
