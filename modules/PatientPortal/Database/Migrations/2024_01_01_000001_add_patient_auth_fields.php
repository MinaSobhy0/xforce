<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'remember_token')) {
                $table->rememberToken();
            }
            if (!Schema::hasColumn('patients', 'portal_enabled')) {
                $table->boolean('portal_enabled')->default(true)->after('is_vip');
            }
            if (!Schema::hasColumn('patients', 'last_portal_login_at')) {
                $table->timestamp('last_portal_login_at')->nullable()->after('portal_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['remember_token', 'portal_enabled', 'last_portal_login_at']);
        });
    }
};
