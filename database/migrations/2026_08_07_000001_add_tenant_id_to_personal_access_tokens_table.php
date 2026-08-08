<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bind API tokens to the tenant that issued them.
 *
 * personal_access_tokens lives in the public schema and is shared by every
 * tenant, but `users` is per-tenant-schema. Resolving a token's tokenable
 * therefore runs against whatever schema search_path is currently set to —
 * which the mobile API derives from the caller-supplied X-Tenant-Slug
 * header. Without a tenant stamp on the token, a token issued for user 42
 * in tenant A authenticates as the unrelated user 42 in tenant B.
 *
 * Nullable because the column is also written by non-tenant token issuers;
 * EnsureTokenMatchesTenant treats NULL as untrusted on the mobile surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
