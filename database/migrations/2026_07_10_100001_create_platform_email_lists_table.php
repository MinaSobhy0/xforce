<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-scoped mailing lists — one row per audience segment the
 * SuperAdmin panel can compose a campaign to. Rows live in the public
 * schema; the tenant-side Modules\Marketing\* tables are separate.
 *
 * kind = 'manual' → members maintained by hand / CSV import.
 * kind = 'dynamic' → members auto-generated from a source query
 * (e.g. "owner-users of active tenants") — the source + filter live in
 * dynamic_source + dynamic_filters and refresh at campaign materialize.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('kind', 20)->default('manual');
            $table->string('dynamic_source', 100)->nullable();
            $table->jsonb('dynamic_filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_lists');
    }
};
