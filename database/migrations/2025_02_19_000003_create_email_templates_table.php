<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->jsonb('subject'); // translatable
            $table->jsonb('body'); // translatable
            $table->string('trigger');
            $table->boolean('is_active')->default(true);
            $table->jsonb('variables')->nullable();
            $table->timestamps();

            $table->index('trigger');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
