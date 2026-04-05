<?php

namespace Modules\Website\Models;

use Illuminate\Support\Facades\Cache;
use XLinic\Framework\Core\Model\BaseModel;

class WebsiteSetting extends BaseModel
{
    protected $table = 'website_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        $value = $setting->value;

        // If it's a simple value (not translatable), return directly
        if (!is_array($value)) {
            return $value;
        }

        // Check if it's a translatable value
        $locale = app()->getLocale();
        if (isset($value[$locale])) {
            return $value[$locale];
        }

        if (isset($value['en'])) {
            return $value['en'];
        }

        // Return the first value or the array itself
        return $value;
    }

    /**
     * Get raw setting value (without locale resolution).
     */
    public static function getRaw(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting?->value ?? $default;
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Clear cache
        Cache::forget("website_settings_{$key}");

        return $setting;
    }

    /**
     * Get all settings as key-value pairs.
     */
    public static function getAllSettings(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }

    /**
     * Get all settings formatted for the frontend.
     */
    public static function getAllForTenant(): array
    {
        $settings = static::all();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->value;
        }

        // Apply defaults for missing keys
        $defaults = [
            'logo' => null,
            'favicon' => null,
            'primary_color' => config('website.default_colors.primary', '#3B82F6'),
            'secondary_color' => config('website.default_colors.secondary', '#10B981'),
            'accent_color' => config('website.default_colors.accent', '#F59E0B'),
            'social_facebook' => null,
            'social_instagram' => null,
            'social_twitter' => null,
            'social_linkedin' => null,
            'social_youtube' => null,
            'social_tiktok' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'contact_address' => null,
            'google_maps_embed' => null,
            'custom_css' => null,
            'custom_head' => null,
            'footer_text' => null,
        ];

        return array_merge($defaults, $result);
    }

    /**
     * Check if a setting exists.
     */
    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Remove a setting.
     */
    public static function remove(string $key): bool
    {
        return static::where('key', $key)->delete() > 0;
    }
}
