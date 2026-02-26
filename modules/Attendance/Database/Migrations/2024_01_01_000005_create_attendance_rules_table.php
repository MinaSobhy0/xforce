<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('working_schedule_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 50)->index();
            $table->text('description')->nullable();
            $table->string('category', 50); // late_checkin, early_checkout, missed_checkin, etc.
            $table->boolean('is_active')->default(true);
            $table->integer('sequence')->default(0);
            $table->boolean('auto_apply')->default(false);
            $table->boolean('send_notification')->default(true);
            $table->boolean('notify_manager')->default(false);
            $table->boolean('notify_hr')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('working_schedule_id')
                ->references('id')
                ->on('working_schedules')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_rules');
    }
};
