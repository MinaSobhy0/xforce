<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->jsonb('title'); // Translatable
            $table->jsonb('body'); // Translatable
            $table->string('type', 20)->default('info'); // info, feature, maintenance, urgent
            $table->jsonb('target_plans')->nullable(); // Plan IDs or codes, null = all
            $table->string('delivery_method', 20)->default('in_app'); // in_app, email, both
            $table->string('status', 20)->default('draft'); // draft, scheduled, sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('read_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
