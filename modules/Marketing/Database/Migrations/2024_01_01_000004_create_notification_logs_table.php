<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('patient_id')->nullable();
            $table->string('channel', 20); // whatsapp, sms, email
            $table->string('type', 50); // appointment_confirmation, reminder, campaign, etc.
            $table->foreignId('template_id')->nullable();
            $table->foreignId('campaign_id')->nullable();
            $table->string('recipient_address'); // phone or email
            $table->string('subject')->nullable(); // for email
            $table->text('content');
            $table->jsonb('variables_json')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->jsonb('provider_response_json')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('cost_minor')->default(0); // cost in minor units
            $table->string('reference_type')->nullable(); // polymorphic: appointment, invoice
            $table->foreignId('reference_id')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'type']);
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
