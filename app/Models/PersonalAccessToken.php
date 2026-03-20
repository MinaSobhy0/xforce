<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Custom PersonalAccessToken that uses the central (public) database connection.
 *
 * This is necessary because in a multi-tenant PostgreSQL schema-per-tenant setup,
 * the User model uses the 'tenant' connection with a dynamic search_path. However,
 * the personal_access_tokens table exists only in the public schema, not in tenant
 * schemas. By forcing this model to use the 'pgsql' connection, tokens are always
 * stored in and retrieved from the public.personal_access_tokens table.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The database connection that should be used by the model.
     * Uses the central 'pgsql' connection which points to the public schema.
     *
     * @var string
     */
    protected $connection = 'pgsql';
}
