<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Central schema - interactive screen guides definitions.
     */
    public function up(): void
    {
        Schema::connection('pgsql')->create('help_screen_guides', function (Blueprint $table) {
            $table->id();
            $table->string('screen_key')->index(); // e.g., 'patients.index', 'appointments.create'
            $table->string('panel')->default('tenant')->index(); // 'tenant', 'super-admin', 'admin'
            $table->jsonb('title'); // {'en': '...', 'ar': '...'}
            $table->jsonb('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_first_visit')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['screen_key', 'panel']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_screen_guides');
    }
};
