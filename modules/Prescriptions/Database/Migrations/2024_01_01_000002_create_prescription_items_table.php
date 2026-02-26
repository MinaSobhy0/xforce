<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('prescription_id')->index();

            // Medication details
            $table->string('medication_name');
            $table->string('generic_name')->nullable();
            $table->string('dosage')->nullable();
            $table->string('dosage_unit')->nullable();
            $table->string('form')->nullable();

            // Dosing schedule
            $table->string('frequency')->nullable();
            $table->integer('duration')->nullable();
            $table->string('duration_unit')->nullable();

            // Quantity
            $table->integer('quantity')->nullable();

            // Administration
            $table->string('route')->nullable();
            $table->string('instructions')->nullable();
            $table->text('special_instructions')->nullable();

            // Refills
            $table->integer('refills_allowed')->default(0);

            // Ordering
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Foreign key
            $table->foreign('prescription_id')
                ->references('id')
                ->on('prescriptions')
                ->onDelete('cascade');

            // Composite indexes
            $table->index(['tenant_id', 'prescription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
