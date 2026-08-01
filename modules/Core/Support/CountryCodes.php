<?php

namespace Modules\Core\Support;

class CountryCodes
{
    /**
     * Common country codes for MENA region and international.
     */
    public const CODES = [
        '+20' => ['name' => 'Egypt', 'code' => 'EG', 'flag' => '🇪🇬'],
        '+966' => ['name' => 'Saudi Arabia', 'code' => 'SA', 'flag' => '🇸🇦'],
        '+971' => ['name' => 'UAE', 'code' => 'AE', 'flag' => '🇦🇪'],
        '+974' => ['name' => 'Qatar', 'code' => 'QA', 'flag' => '🇶🇦'],
        '+973' => ['name' => 'Bahrain', 'code' => 'BH', 'flag' => '🇧🇭'],
        '+965' => ['name' => 'Kuwait', 'code' => 'KW', 'flag' => '🇰🇼'],
        '+968' => ['name' => 'Oman', 'code' => 'OM', 'flag' => '🇴🇲'],
        '+962' => ['name' => 'Jordan', 'code' => 'JO', 'flag' => '🇯🇴'],
        '+961' => ['name' => 'Lebanon', 'code' => 'LB', 'flag' => '🇱🇧'],
        '+963' => ['name' => 'Syria', 'code' => 'SY', 'flag' => '🇸🇾'],
        '+964' => ['name' => 'Iraq', 'code' => 'IQ', 'flag' => '🇮🇶'],
        '+212' => ['name' => 'Morocco', 'code' => 'MA', 'flag' => '🇲🇦'],
        '+213' => ['name' => 'Algeria', 'code' => 'DZ', 'flag' => '🇩🇿'],
        '+216' => ['name' => 'Tunisia', 'code' => 'TN', 'flag' => '🇹🇳'],
        '+218' => ['name' => 'Libya', 'code' => 'LY', 'flag' => '🇱🇾'],
        '+249' => ['name' => 'Sudan', 'code' => 'SD', 'flag' => '🇸🇩'],
        '+970' => ['name' => 'Palestine', 'code' => 'PS', 'flag' => '🇵🇸'],
        '+967' => ['name' => 'Yemen', 'code' => 'YE', 'flag' => '🇾🇪'],
        '+1' => ['name' => 'USA/Canada', 'code' => 'US', 'flag' => '🇺🇸'],
        '+44' => ['name' => 'UK', 'code' => 'GB', 'flag' => '🇬🇧'],
        '+49' => ['name' => 'Germany', 'code' => 'DE', 'flag' => '🇩🇪'],
        '+33' => ['name' => 'France', 'code' => 'FR', 'flag' => '🇫🇷'],
        '+39' => ['name' => 'Italy', 'code' => 'IT', 'flag' => '🇮🇹'],
        '+34' => ['name' => 'Spain', 'code' => 'ES', 'flag' => '🇪🇸'],
        '+31' => ['name' => 'Netherlands', 'code' => 'NL', 'flag' => '🇳🇱'],
        '+90' => ['name' => 'Turkey', 'code' => 'TR', 'flag' => '🇹🇷'],
        '+91' => ['name' => 'India', 'code' => 'IN', 'flag' => '🇮🇳'],
        '+92' => ['name' => 'Pakistan', 'code' => 'PK', 'flag' => '🇵🇰'],
        '+86' => ['name' => 'China', 'code' => 'CN', 'flag' => '🇨🇳'],
        '+81' => ['name' => 'Japan', 'code' => 'JP', 'flag' => '🇯🇵'],
        '+82' => ['name' => 'South Korea', 'code' => 'KR', 'flag' => '🇰🇷'],
        '+61' => ['name' => 'Australia', 'code' => 'AU', 'flag' => '🇦🇺'],
        '+55' => ['name' => 'Brazil', 'code' => 'BR', 'flag' => '🇧🇷'],
        '+27' => ['name' => 'South Africa', 'code' => 'ZA', 'flag' => '🇿🇦'],
        '+234' => ['name' => 'Nigeria', 'code' => 'NG', 'flag' => '🇳🇬'],
        '+254' => ['name' => 'Kenya', 'code' => 'KE', 'flag' => '🇰🇪'],
    ];

    /**
     * Get all country codes as options for select.
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::CODES as $code => $data) {
            $options[$code] = "{$data['flag']} {$code} ({$data['name']})";
        }

        return $options;
    }

    /**
     * Get compact options (flag + code only) for inline selects.
     */
    public static function compactOptions(): array
    {
        $options = [];
        foreach (self::CODES as $code => $data) {
            $options[$code] = "{$data['flag']} {$code}";
        }

        return $options;
    }

    /**
     * Get options without emoji (for compatibility).
     */
    public static function optionsSimple(): array
    {
        $options = [];
        foreach (self::CODES as $code => $data) {
            $options[$code] = "{$code} ({$data['name']})";
        }

        return $options;
    }

    /**
     * Get country code by ISO code.
     */
    public static function getByIsoCode(string $isoCode): ?string
    {
        $isoCode = strtoupper($isoCode);
        foreach (self::CODES as $code => $data) {
            if ($data['code'] === $isoCode) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get country code by country name.
     */
    public static function getByName(string $name): ?string
    {
        $name = strtolower($name);
        foreach (self::CODES as $code => $data) {
            if (strtolower($data['name']) === $name) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get default country code (Egypt).
     */
    public static function default(): string
    {
        return '+20';
    }

    /**
     * Get the default dial code for the current clinic, derived from the
     * tenant's country. The tenants table stores the ISO code (`'EG'`,
     * `'KW'`, …), so try getByIsoCode() first; fall back to getByName()
     * for legacy rows that still hold the full country name (`'Egypt'`).
     * Falls through to the global default when nothing matches.
     */
    public static function defaultForTenant(): string
    {
        // XLinic uses schema-based tenancy — filament()->getTenant() (which
        // is Filament's built-in multi-tenancy) always returns null here.
        // The real current tenant is resolved by IdentifyTenant middleware
        // and exposed via the current_tenant() helper.
        $tenant = function_exists('current_tenant') ? current_tenant() : null;

        if ($tenant && ! empty($tenant->country)) {
            $code = self::getByIsoCode($tenant->country)
                ?? self::getByName($tenant->country);
            if ($code !== null) {
                return $code;
            }
        }

        return self::default();
    }

    /**
     * Validate a country code.
     */
    public static function isValid(string $code): bool
    {
        if (! str_starts_with($code, '+')) {
            $code = '+'.$code;
        }

        return isset(self::CODES[$code]);
    }

    /**
     * Format phone number with country code.
     */
    public static function formatPhone(string $phone, string $countryCode = '+20'): string
    {
        // Remove any non-numeric characters from phone
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // Ensure country code starts with +
        if (! str_starts_with($countryCode, '+')) {
            $countryCode = '+'.$countryCode;
        }

        return $countryCode.$phone;
    }
}
