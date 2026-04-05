<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->index();
            $table->jsonb('title')->comment('Translatable: {en: "...", ar: "..."}');
            $table->jsonb('meta_title')->nullable()->comment('SEO title');
            $table->jsonb('meta_description')->nullable()->comment('SEO description');
            $table->boolean('is_homepage')->default(false)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Only one homepage per tenant
            $table->unique(['is_homepage', 'deleted_at'], 'website_pages_homepage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_pages');
    }
};
