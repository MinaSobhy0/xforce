<?php

namespace Modules\Auth\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * Use tenant database connection for multi-tenancy.
     */
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'module',
    ];
}
