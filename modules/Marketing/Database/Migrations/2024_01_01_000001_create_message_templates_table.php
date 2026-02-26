<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('code', 50)->index();
            $table->jsonb('name'); // translatable
            $table->string('channel', 20); // whatsapp, sms, email
            $table->string('type', 50); // appointment_confirmation, reminder, etc.
            $table->jsonb('subject')->nullable(); // for email
            $table->jsonb('content'); // translatable message body
            $table->string('whatsapp_template_name')->nullable(); // Meta-approved template name
            $table->string('whatsapp_template_namespace')->nullable();
            $table->jsonb('variables_json')->nullable(); // available variables
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'channel', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
