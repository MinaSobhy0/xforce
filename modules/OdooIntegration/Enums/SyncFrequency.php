<?php

namespace Modules\OdooIntegration\Enums;

enum SyncFrequency: string
{
    case MANUAL = 'manual';
    case HOURLY = 'hourly';
    case DAILY = 'daily';
    case EVERY_TWO_DAYS = 'every_two_days';
    case REALTIME = 'realtime';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::HOURLY => 'Every Hour',
            self::DAILY => 'Daily',
            self::EVERY_TWO_DAYS => 'Every 2 Days',
            self::REALTIME => 'Real-time',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MANUAL => 'Only sync when manually triggered',
            self::HOURLY => 'Sync automatically every hour',
            self::DAILY => 'Sync automatically once per day',
            self::EVERY_TWO_DAYS => 'Sync automatically every two days',
            self::REALTIME => 'Sync immediately on changes (uses queue)',
        };
    }

    public function cronExpression(): ?string
    {
        return match ($this) {
            self::MANUAL => null,
            self::HOURLY => '0 * * * *',
            self::DAILY => '0 2 * * *',
            self::EVERY_TWO_DAYS => '0 3 */2 * *',
            self::REALTIME => null,
        };
    }

    public function isScheduled(): bool
    {
        return in_array($this, [self::HOURLY, self::DAILY, self::EVERY_TWO_DAYS]);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->all();
    }
}
