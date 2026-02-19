<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PostgresBoolean implements CastsAttributes
{
    /**
     * Cast the given value (from database).
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Prepare the given value for storage (to database).
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        // Return actual SQL boolean expression for PostgreSQL
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? true : false;
    }
}
