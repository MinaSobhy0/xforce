<?php

namespace Modules\Inventory\Enums;

enum ProductType: string
{
    case STORABLE = 'storable';    // Full inventory tracking with stock levels
    case CONSUMABLE = 'consumable'; // No detailed quantity tracking, assumed always available

    public function label(): string
    {
        return match ($this) {
            self::STORABLE => __('inventory::inventory.product_types.storable'),
            self::CONSUMABLE => __('inventory::inventory.product_types.consumable'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::STORABLE => 'success',
            self::CONSUMABLE => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::STORABLE => 'heroicon-o-cube',
            self::CONSUMABLE => 'heroicon-o-arrow-path',
        };
    }

    /**
     * Whether this product type tracks inventory levels.
     */
    public function tracksInventory(): bool
    {
        return match ($this) {
            self::STORABLE => true,
            self::CONSUMABLE => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }
}
