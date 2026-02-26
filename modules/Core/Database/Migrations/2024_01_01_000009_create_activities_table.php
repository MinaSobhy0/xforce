<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable(); // Keep as UUID for grouping
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
