<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Language a campaign is written in (drives the AI prompt to write
 * in that language and the mail template to switch to RTL for ar).
 * Default 'en'; a bilingual outreach uses two campaigns, one per lang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_email_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_email_campaigns', 'language')) {
                $table->string('language', 5)->default('en')->after('preheader');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_email_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('platform_email_campaigns', 'language')) {
                $table->dropColumn('language');
            }
        });
    }
};
