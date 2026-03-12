<?php

namespace Modules\Auth\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * Use tenant database connection for multi-tenancy.
     */
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'description',
        'level',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'level' => 'integer',
    ];
}
