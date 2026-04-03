<?php

namespace Modules\OdooIntegration\Services\Transform;

use Carbon\Carbon;
use DateTimeZone;

class TimezoneConverter
{
    /**
     * Convert a datetime string between timezones.
     */
    public function convert(
        string|null $datetime,
        string $fromTz,
        string $toTz,
        string $format = 'Y-m-d H:i:s'
    ): ?string {
        if (empty($datetime)) {
            return null;
        }

        try {
            // Parse the datetime in the source timezone
            $carbon = Carbon::parse($datetime, new DateTimeZone($fromTz));

            // Convert to target timezone
            $carbon->setTimezone(new DateTimeZone($toTz));

            return $carbon->format($format);
        } catch (\Exception $e) {
            // Return original on error
            return $datetime;
        }
    }

    /**
     * Convert from Odoo timezone to local timezone.
     */
    public function fromOdoo(
        string|null $datetime,
        string $odooTz = 'UTC',
        ?string $localTz = null
    ): ?string {
        $localTz = $localTz ?? config('app.timezone', 'Africa/Cairo');
        return $this->convert($datetime, $odooTz, $localTz);
    }

    /**
     * Convert from local timezone to Odoo timezone.
     */
    public function toOdoo(
        string|null $datetime,
        ?string $localTz = null,
        string $odooTz = 'UTC'
    ): ?string {
        $localTz = $localTz ?? config('app.timezone', 'Africa/Cairo');
        return $this->convert($datetime, $localTz, $odooTz);
    }

    /**
     * Get the current datetime in Odoo format.
     */
    public function nowForOdoo(string $odooTz = 'UTC'): string
    {
        return Carbon::now(new DateTimeZone($odooTz))->format('Y-m-d H:i:s');
    }

    /**
     * Parse Odoo datetime to Carbon instance.
     */
    public function parseOdooDatetime(
        string|null $datetime,
        string $odooTz = 'UTC'
    ): ?Carbon {
        if (empty($datetime) || $datetime === false) {
            return null;
        }

        try {
            return Carbon::parse($datetime, new DateTimeZone($odooTz));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format Carbon instance for Odoo.
     */
    public function formatForOdoo(
        Carbon|null $carbon,
        string $odooTz = 'UTC'
    ): ?string {
        if (!$carbon) {
            return null;
        }

        return $carbon->setTimezone(new DateTimeZone($odooTz))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Get list of common timezones.
     */
    public static function getTimezoneOptions(): array
    {
        return [
            'UTC' => 'UTC',
            'Africa/Cairo' => 'Africa/Cairo (EET)',
            'Europe/London' => 'Europe/London (GMT/BST)',
            'Europe/Paris' => 'Europe/Paris (CET)',
            'America/New_York' => 'America/New York (EST)',
            'America/Los_Angeles' => 'America/Los Angeles (PST)',
            'Asia/Dubai' => 'Asia/Dubai (GST)',
            'Asia/Riyadh' => 'Asia/Riyadh (AST)',
            'Asia/Tokyo' => 'Asia/Tokyo (JST)',
            'Australia/Sydney' => 'Australia/Sydney (AEST)',
        ];
    }
}
