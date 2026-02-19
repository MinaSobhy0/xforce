<?php

namespace Modules\Core\Models;

enum TenantStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::SUSPENDED => __('Suspended'),
            self::INACTIVE => __('Inactive'),
            self::PENDING => __('Pending'),
            self::EXPIRED => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::INACTIVE => 'gray',
            self::PENDING => 'info',
            self::EXPIRED => 'danger',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ACTIVE => __('Tenant is active and operational'),
            self::SUSPENDED => __('Tenant is temporarily suspended'),
            self::INACTIVE => __('Tenant is inactive'),
            self::PENDING => __('Tenant setup is pending'),
            self::EXPIRED => __('Tenant subscription has expired'),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($status) => [$status->value => $status->label()])
            ->toArray();
    }
}