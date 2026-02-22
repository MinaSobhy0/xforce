<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_breaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('attendance_id')->index();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('attendance_id')
                ->references('id')
                ->on('attendances')
                ->onDelete('cascade');

            // Indexes
            $table->index(['tenant_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
    }
};
