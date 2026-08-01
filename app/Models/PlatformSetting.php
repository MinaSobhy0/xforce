<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $connection = 'central';

    protected $table = 'public.platform_settings';

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
            // A row that fails to decrypt must not 500 every screen that
            // reads settings — treat it as unset so it can be re-entered.
            try {
                return decrypt($value);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Platform setting '{$this->key}' could not be decrypted; treating as unset.");

                return null;
            }
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
        try {
            $settings = Cache::remember('platform_settings', 3600, function () {
                return self::all()->keyBy('key');
            });

            return $settings->get($key)?->value ?? $default;
        } catch (\Illuminate\Database\QueryException $e) {
            // Table doesn't exist yet (during migrations)
            return $default;
        }
    }

    public static function set(string $key, $value, string $group = 'general', string $type = 'string', bool $encrypted = false): self
    {
        $setting = self::firstOrNew(['key' => $key]);
        $setting->group = $group;
        $setting->type = $type;
        // is_encrypted MUST be assigned before value: the value mutator
        // decides whether to encrypt based on it. The old implementation
        // flipped the flag after saving, so the first save of an encrypted
        // setting stored plaintext flagged as encrypted — which then blew
        // up decrypt() on every subsequent read.
        $setting->is_encrypted = $encrypted;
        $setting->value = $value;
        $setting->save();

        // Invalidate the get() cache so the new value is visible on the
        // very next read (otherwise it stays stale for up to an hour and
        // the admin form appears not to have saved on reload).
        \Illuminate\Support\Facades\Cache::forget('platform_settings');

        return $setting;
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

        if (! $setting) {
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
