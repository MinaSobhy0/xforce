<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('category_id');
            $table->jsonb('name'); // Translatable: {'en': '...', 'ar': '...'}
            $table->string('abbreviation', 20); // Short code: kg, pcs, ml
            $table->string('uom_type', 20)->default('reference'); // 'bigger', 'reference', 'smaller'
            $table->decimal('ratio', 20, 10)->default(1.0); // Conversion ratio to reference unit
            $table->boolean('is_reference')->default(false); // One per category
            $table->boolean('is_active')->default(true);
            $table->decimal('rounding_precision', 10, 6)->default(0.01);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('uom_categories')->cascadeOnDelete();
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uoms');
    }
};
