<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->jsonb('name'); // translatable
            $table->jsonb('description')->nullable(); // translatable
            $table->string('trigger_type', 50); // appointment_confirmed, invoice_paid, etc.
            $table->uuid('template_id');
            $table->string('channel', 20); // whatsapp, sms, email
            $table->string('timing_type', 20)->default('immediate'); // immediate, before, after
            $table->integer('timing_value')->default(0);
            $table->string('timing_unit', 20)->default('hours'); // minutes, hours, days
            $table->jsonb('conditions_json')->nullable(); // additional filter conditions
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->foreign('template_id')
                ->references('id')
                ->on('message_templates')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'trigger_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
