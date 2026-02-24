<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameter_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('service_id');
            $table->string('preset_name', 200);
            $table->text('description')->nullable();
            $table->json('preset_values'); // Key-value pairs of parameter values
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->onDelete('cascade');

            $table->index(['service_id', 'is_active']);
            $table->index(['tenant_id', 'service_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_presets');
    }
};
