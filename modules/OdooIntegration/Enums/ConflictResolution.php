<?php

namespace Modules\OdooIntegration\Enums;

enum ConflictResolution: string
{
    case NEWEST_WINS = 'newest_wins';
    case LOCAL_WINS = 'local_wins';
    case ODOO_WINS = 'odoo_wins';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match($this) {
            self::NEWEST_WINS => 'Newest Wins',
            self::LOCAL_WINS => 'Local Always Wins',
            self::ODOO_WINS => 'Odoo Always Wins',
            self::MANUAL => 'Manual Review',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::NEWEST_WINS => 'Most recently modified record wins',
            self::LOCAL_WINS => 'XForce data always takes precedence',
            self::ODOO_WINS => 'Odoo data always takes precedence',
            self::MANUAL => 'Create conflict record for manual resolution',
        };
    }

    public function requiresReview(): bool
    {
        return $this === self::MANUAL;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }

    public static function optionsWithDescriptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label() . ' - ' . $case->description(),
        ])->all();
    }
}
