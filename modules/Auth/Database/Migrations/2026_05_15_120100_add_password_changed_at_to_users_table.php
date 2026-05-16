<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `password_changed_at` to users. Written by the security-audit
     * code paths (CreateUser, EditUser, ViewUser reset action,
     * UserResource bulk reset, ManageProfile, and the tenant-users
     * relation manager) whenever a password is set or rotated. Used
     * alongside `password_expires_at` to enforce password-expiry policy.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_changed_at');
        });
    }
};
