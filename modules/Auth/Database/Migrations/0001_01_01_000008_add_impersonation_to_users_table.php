<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'impersonation_token')) {
                $table->string('impersonation_token', 64)->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'impersonation_token_expires_at')) {
                $table->timestamp('impersonation_token_expires_at')->nullable()->after('impersonation_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['impersonation_token', 'impersonation_token_expires_at']);
        });
    }
};
