<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hash-keyed cache of media we've uploaded to Meta.
 *
 * Meta's /{phone_number_id}/media endpoint returns a media_id valid for
 * ~30 days. Re-uploading the same bytes wastes bandwidth and a round
 * trip; cache the sha256 → media_id mapping so the second send of the
 * same logo/PDF is just a JSON post with type=image, image.id=cached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_media_cache', function (Blueprint $table) {
            $table->string('sha256', 64)->primary();
            $table->string('media_id', 128);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->index('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_media_cache');
    }
};
