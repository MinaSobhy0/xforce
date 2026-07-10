<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-campaign from_address so marketing sends can go from
 * hello@x-linic.com / ibram@x-linic.com etc. — while noreply@ stays
 * reserved for transactional Mailables (contract signing, password
 * resets, welcome-provisioned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_email_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_email_campaigns', 'from_address')) {
                $table->string('from_address')->nullable()->after('preheader');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_email_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('platform_email_campaigns', 'from_address')) {
                $table->dropColumn('from_address');
            }
        });
    }
};
