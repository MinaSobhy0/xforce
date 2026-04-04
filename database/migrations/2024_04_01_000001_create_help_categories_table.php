<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Central schema - platform-wide help categories.
     */
    public function up(): void
    {
        Schema::connection('pgsql')->create('help_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('help_categories')->nullOnDelete();
            $table->jsonb('name'); // {'en': '...', 'ar': '...'}
            $table->jsonb('description')->nullable();
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // heroicon name
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('help_categories');
    }
};
