<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['visit_id', 'appointment_id']);
            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_appointments');
    }
};
