<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standard Laravel password-reset token store. Used by the Forgot
     * Password / Reset Password flow in modules/Auth/routes/web.php, and
     * by the security hook on User::updating that invalidates pending
     * tokens whenever a password changes.
     *
     * Lives in each tenant schema (same connection as `users`) so tokens
     * are naturally scoped to the tenant.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
