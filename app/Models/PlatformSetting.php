<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public const TYPES = [
        'string' => 'Text',
        'integer' => 'Number',
        'boolean' => 'Yes/No',
        'json' => 'JSON',
        'file' => 'File',
    ];

    public const GROUPS = [
        'general' => 'General',
        'trial' => 'Trial & Onboarding',
        'payment' => 'Payment',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'email' => 'Email',
        'storage' => 'Storage',
        'branding' => 'Branding',
        'backup' => 'Backup & Maintenance',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('platform_settings');
        });
    }

    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            return decrypt($value);
        }

        return match ($this->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function setValueAttribute($value): void
    {
        if ($this->is_encrypted) {
            $this->attributes['value'] = encrypt($value);
            return;
        }

        $this->attributes['value'] = match ($this->type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    public function setIsEncryptedAttribute($value): void
    {
        $this->attributes['is_encrypted'] = (bool) $value;
    }

    public static function get(string $key, $default = null)
    {
        $settings = Cache::remember('platform_settings', 3600, function () {
            return self::all()->keyBy('key');
        });

        return $settings->get($key)?->value ?? $default;
    }

    public static function set(string $key, $value, string $group = 'general', string $type = 'string', bool $encrypted = false): self
    {
        $setting = self::where('key', $key)->first();
        $encryptedValue = $encrypted ? 'true' : 'false';

        if ($setting) {
            $setting->group = $group;
            $setting->type = $type;
            $setting->value = $value;
            $setting->save();

            \DB::statement("UPDATE platform_settings SET is_encrypted = {$encryptedValue} WHERE key = ?", [$key]);
            $setting->refresh();
            return $setting;
        }

        // Insert without is_encrypted, then update it
        \DB::statement("
            INSERT INTO platform_settings (id, key, \"group\", value, type, is_encrypted, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, {$encryptedValue}, NOW(), NOW())
        ", [
            (string) \Illuminate\Support\Str::uuid(),
            $key,
            $group,
            $type === 'json' ? json_encode($value) : (string) $value,
            $type,
        ]);

        return self::where('key', $key)->first();
    }

    public static function getGroup(string $group): array
    {
        return self::where('group', $group)->get()->pluck('value', 'key')->toArray();
    }

    /**
     * Get an encrypted setting value.
     * Note: The value accessor already handles decryption, but this method
     * bypasses the cache to ensure fresh encrypted values are retrieved.
     */
    public static function getEncrypted(string $key, $default = null)
    {
        $setting = self::where('key', $key)->whereRaw('is_encrypted = true')->first();

        if (!$setting) {
            return $default;
        }

        return $setting->value;
    }

    /**
     * Set an encrypted setting value.
     */
    public static function setEncrypted(string $key, $value, string $group = 'general'): self
    {
        return self::set($key, $value, $group, 'string', true);
    }
}
