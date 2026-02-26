<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('service_id');
            $table->string('parameter_key', 100);
            $table->json('parameter_config');
            /*
             * parameter_config structure:
             * {
             *   "type": "number|text|select|boolean|decimal|date|time|range|textarea",
             *   "label": {"en": "Label", "ar": "التسمية"},
             *   "required": true|false,
             *   "default_value": mixed,
             *   "unit": "nm|J|W|Hz|ms|°C|%|pulses|shots",
             *   "min": number,
             *   "max": number,
             *   "step": number,
             *   "options": [{"value": "x", "label": {"en": "X", "ar": "س"}}],
             *   "help_text": {"en": "Description", "ar": "الوصف"},
             *   "placeholder": {"en": "Enter value", "ar": "أدخل القيمة"},
             *   "validation": {"pattern": "regex", "min_length": n, "max_length": n}
             * }
             */
            $table->boolean('is_required')->default(false);
            $table->string('category', 50)->nullable(); // equipment_settings, clinical, safety, outcomes
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');

            $table->unique(['service_id', 'parameter_key']);
            $table->index(['service_id', 'is_active']);
            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_parameters');
    }
};
