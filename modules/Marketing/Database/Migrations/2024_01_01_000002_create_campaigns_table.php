<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->jsonb('name'); // translatable
            $table->jsonb('description')->nullable(); // translatable
            $table->foreignId('template_id');
            $table->string('channel', 20); // whatsapp, sms, email
            $table->jsonb('audience_filters_json')->nullable(); // filter criteria
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('read_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('template_id')
                ->references('id')
                ->on('message_templates')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
