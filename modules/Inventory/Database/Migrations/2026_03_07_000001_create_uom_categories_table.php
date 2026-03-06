<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('uom_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->jsonb('name'); // Translatable: {'en': '...', 'ar': '...'}
            $table->jsonb('description')->nullable(); // Translatable
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uom_categories');
    }
};
