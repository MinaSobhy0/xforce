<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('treatment_id')->constrained('treatments')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->uuid('practitioner_id')->nullable(); // Nullable = any practitioner
            $table->jsonb('preferred_days')->nullable(); // [0, 1, 2] for Sun, Mon, Tue
            $table->jsonb('preferred_times')->nullable(); // ['morning', 'afternoon', 'evening']
            $table->integer('priority')->default(2); // 1=low, 2=normal, 3=high, 4=urgent
            $table->text('notes')->nullable();
            $table->string('status')->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('patient_id');
            $table->index('treatment_id');
            $table->index('branch_id');
            $table->index('practitioner_id');
            $table->index('status');
            $table->index('priority');
            $table->index(['status', 'priority']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist');
    }
};
