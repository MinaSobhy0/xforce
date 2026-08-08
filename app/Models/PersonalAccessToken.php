<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Custom PersonalAccessToken that always uses the public schema.
 *
 * In a multi-tenant PostgreSQL schema-per-tenant setup, the search_path is
 * dynamically set to tenant schemas. However, personal_access_tokens exists
 * only in the public schema. By using a fully qualified table name
 * (public.personal_access_tokens), tokens are always stored in the correct
 * location regardless of the current search_path.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The table associated with the model.
     * Uses fully qualified name to always target public schema.
     *
     * @var string
     */
    protected $table = 'public.personal_access_tokens';

    /**
     * The tenant that issued this token. Because `users` is per-tenant-schema
     * while this table is shared, a token with no tenant stamp can be
     * replayed against another tenant's schema and resolve to a different
     * person with the same numeric id. See EnsureTokenMatchesTenant.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'token',
        'abilities',
        'expires_at',
    ];
}
