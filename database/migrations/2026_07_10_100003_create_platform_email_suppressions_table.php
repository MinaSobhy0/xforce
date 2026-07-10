<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global suppression list — an address here is never emailed from the
 * platform regardless of which list it belongs to. Checked in every
 * send job before dispatch.
 *
 * reason:
 *   'unsubscribed' — clicked the List-Unsubscribe link
 *   'bounced'      — recipient's mail server rejected (hard bounce)
 *   'complained'   — marked our message as spam
 *   'manual'       — added by staff (do-not-contact request, VIP list, etc.)
 *
 * Once an address is suppressed the row stays forever (or until an
 * operator explicitly rehabilitates it via the Filament resource).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('reason', 20);
            $table->foreignId('campaign_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('added_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('email');
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_suppressions');
    }
};
