<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('code', 10)->unique();
            $table->string('target_url', 2048);
            $table->string('action', 30)->nullable();
            $table->foreignId('appointment_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('clicks')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
