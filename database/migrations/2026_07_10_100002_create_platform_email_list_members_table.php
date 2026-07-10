<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-list membership. For manual lists these are the actual entries;
 * for dynamic lists they are cached snapshots — the resolver refreshes
 * the cache when a campaign materializes.
 *
 * subscribed_at / unsubscribed_at track per-list opt-out (versus the
 * global suppression table which blocks ALL platform mail to that
 * address). Both checks run at send-time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('platform_email_lists')->cascadeOnDelete();
            $table->string('email');
            $table->string('name_hint')->nullable();
            // Provenance — which record spawned this membership
            // (owner_users:{id}, contact_inquiries:{id}, csv_import, manual).
            $table->string('source_type', 100)->nullable();
            $table->string('source_id', 100)->nullable();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['list_id', 'email']);
            $table->index('email');
            $table->index('unsubscribed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_list_members');
    }
};
