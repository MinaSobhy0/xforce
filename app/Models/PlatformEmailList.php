<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-scoped mailing list. Public schema — bypasses tenancy.
 */
class PlatformEmailList extends Model
{
    use HasFactory;

    public const KIND_MANUAL = 'manual';

    public const KIND_DYNAMIC = 'dynamic';

    public const KINDS = [
        self::KIND_MANUAL => 'Manual list',
        self::KIND_DYNAMIC => 'Dynamic (auto-refreshing)',
    ];

    protected $fillable = [
        'name',
        'description',
        'kind',
        'dynamic_source',
        'dynamic_filters',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'dynamic_filters' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'kind' => self::KIND_MANUAL,
        'is_active' => true,
    ];

    public function members(): HasMany
    {
        return $this->hasMany(PlatformEmailListMember::class, 'list_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(PlatformEmailCampaign::class, 'list_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('unsubscribed_at');
    }
}
