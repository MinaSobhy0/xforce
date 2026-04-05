<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->string('type', 50)->index()->comment('Block type: hero, features, services, etc.');
            $table->jsonb('content')->nullable()->comment('Block-specific content');
            $table->jsonb('settings')->nullable()->comment('Block styling/options');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_blocks');
    }
};
