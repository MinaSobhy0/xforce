<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'favicon_url')) {
                $table->string('favicon_url')->nullable()->after('logo_url');
            }
            if (!Schema::hasColumn('tenants', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('logo_url');
            }
            if (!Schema::hasColumn('tenants', 'favicon_path')) {
                $table->string('favicon_path')->nullable()->after('favicon_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['favicon_url', 'logo_path', 'favicon_path']);
        });
    }
};
