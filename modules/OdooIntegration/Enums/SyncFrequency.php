<?php

namespace Modules\OdooIntegration\Enums;

enum SyncFrequency: string
{
    case MANUAL = 'manual';
    case HOURLY = 'hourly';
    case DAILY = 'daily';
    case REALTIME = 'realtime';

    public function label(): string
    {
        return match($this) {
            self::MANUAL => 'Manual',
            self::HOURLY => 'Every Hour',
            self::DAILY => 'Daily',
            self::REALTIME => 'Real-time',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::MANUAL => 'Only sync when manually triggered',
            self::HOURLY => 'Sync automatically every hour',
            self::DAILY => 'Sync automatically once per day',
            self::REALTIME => 'Sync immediately on changes (uses queue)',
        };
    }

    public function cronExpression(): ?string
    {
        return match($this) {
            self::MANUAL => null,
            self::HOURLY => '0 * * * *',
            self::DAILY => '0 2 * * *',
            self::REALTIME => null,
        };
    }

    public function isScheduled(): bool
    {
        return in_array($this, [self::HOURLY, self::DAILY]);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }
}
