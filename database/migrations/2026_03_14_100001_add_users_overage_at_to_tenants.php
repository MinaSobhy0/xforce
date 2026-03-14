<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->timestamp('users_overage_at')->nullable()->after('extra_storage_mb');
            $table->boolean('users_overage_notified')->default(false)->after('users_overage_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['users_overage_at', 'users_overage_notified']);
        });
    }
};
