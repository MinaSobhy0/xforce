<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_parameter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('template_name', 200);
            $table->string('template_code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('equipment_category', 100)->nullable();
            $table->jsonb('parameters')->default('[]');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('equipment_category');
            $table->index('is_active');
        });

        // Add parameter_template_id to equipment table
        Schema::table('equipment', function (Blueprint $table) {
            $table->foreignId('parameter_template_id')->nullable()->after('tracking_enabled');

            $table->foreign('parameter_template_id')
                ->references('id')
                ->on('equipment_parameter_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropForeign(['parameter_template_id']);
            $table->dropColumn('parameter_template_id');
        });

        Schema::dropIfExists('equipment_parameter_templates');
    }
};
