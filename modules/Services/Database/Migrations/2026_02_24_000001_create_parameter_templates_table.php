<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable(); // null = system template
            $table->string('template_name', 200);
            $table->string('template_code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('service_category', 100)->nullable();
            $table->json('parameters'); // Array of parameter definitions
            $table->boolean('is_system')->default(false); // Read-only if true
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'is_active']);
            $table->index(['service_category', 'is_active']);
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_templates');
    }
};
