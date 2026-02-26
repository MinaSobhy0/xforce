<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('medical_profile_id');
            $table->enum('allergy_type', ['drug', 'food', 'environmental', 'topical', 'metal', 'latex', 'other']);
            $table->string('allergen');
            $table->enum('severity', ['mild', 'moderate', 'severe', 'life_threatening']);
            $table->text('reaction')->nullable();
            $table->date('discovered_date')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('show_alert')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('medical_profile_id')->references('id')->on('medical_profiles')->cascadeOnDelete();

            $table->index(['medical_profile_id', 'allergy_type']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_allergies');
    }
};
