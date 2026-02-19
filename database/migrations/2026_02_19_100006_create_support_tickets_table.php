<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('ticket_number', 20)->unique();
            $table->string('subject', 255);
            $table->text('description');
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            $table->string('status', 20)->default('open'); // open, in_progress, waiting_customer, resolved, closed
            $table->string('category', 50)->default('other'); // billing, technical, feature_request, bug, account, other
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->uuid('reporter_user_id')->nullable();
            $table->string('reporter_name', 255)->nullable();
            $table->string('reporter_email', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index(['assigned_to', 'status']);
            $table->index(['category', 'status']);
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 255);
            $table->text('message');
            $table->boolean('is_internal')->default(false);
            $table->jsonb('attachments')->nullable();

            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');

            $table->index(['ticket_id', 'created_at']);
            $table->index(['ticket_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};
