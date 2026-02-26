<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('session_id')->unique();
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->timestamp('last_activity');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id']);
            $table->index(['session_id']);
            $table->index(['last_activity']);
            $table->index(['expires_at']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};