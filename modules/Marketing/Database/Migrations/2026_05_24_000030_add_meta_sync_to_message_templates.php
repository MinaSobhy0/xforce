<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks per-WABA Meta template approval state on tenant message_templates.
 *
 * Meta requires every template to be approved per WABA — same content in
 * two clinics' WABAs is still two distinct PENDING→APPROVED submissions.
 *
 * Includes platform_template_id forward-declaration so the catalog adopt
 * flow (Phase F) doesn't need a second migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('meta_template_id', 64)->nullable()->after('whatsapp_template_namespace');
            $table->string('meta_template_status', 32)->nullable()->after('meta_template_id'); // PENDING|APPROVED|REJECTED|PAUSED|DISABLED
            $table->string('meta_template_category', 32)->nullable()->after('meta_template_status'); // MARKETING|UTILITY|AUTHENTICATION
            $table->string('meta_template_language', 16)->nullable()->after('meta_template_category'); // en_US|ar|...
            $table->timestamp('meta_synced_at')->nullable()->after('meta_template_language');
            $table->text('meta_last_error')->nullable()->after('meta_synced_at');

            // Forward-declared for Phase F (platform catalog). Nullable FK to
            // platform_whatsapp_templates which doesn't exist yet — added as
            // a plain bigint so this migration runs cleanly.
            $table->unsignedBigInteger('platform_template_id')->nullable()->after('meta_last_error');

            $table->index('meta_template_status');
            $table->index('platform_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropIndex(['meta_template_status']);
            $table->dropIndex(['platform_template_id']);
            $table->dropColumn([
                'meta_template_id',
                'meta_template_status',
                'meta_template_category',
                'meta_template_language',
                'meta_synced_at',
                'meta_last_error',
                'platform_template_id',
            ]);
        });
    }
};
