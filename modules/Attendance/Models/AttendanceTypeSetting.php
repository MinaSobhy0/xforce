<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;
use XLinic\Framework\Core\Model\BaseModel;

class AttendanceTypeSetting extends BaseModel
{
    protected $table = 'attendance_type_settings';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'type',
        'is_enabled',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_enabled' => false,
        'settings' => '{}',
    ];

    /**
     * Default settings for each attendance type.
     */
    public const DEFAULT_SETTINGS = [
        Attendance::TYPE_GEOFENCE => [
            'radius_meters' => 100,
            'require_high_accuracy' => true,
            'min_accuracy_meters' => 50,
            'allow_mock_location' => false,
            'check_on_checkout' => true,
            'locations' => [], // Array of {lat, lng, name, radius}
        ],
        Attendance::TYPE_QR_STATIC => [
            'qr_content' => null, // Will be auto-generated
            'qr_secret' => null, // For validation
            'allow_camera_only' => true,
            'show_qr_in_app' => false,
            'require_location' => false,
        ],
        Attendance::TYPE_QR_DYNAMIC => [
            'refresh_interval_seconds' => 30,
            'validity_seconds' => 60,
            'algorithm' => 'totp', // totp or hotp
            'secret_key' => null, // Will be auto-generated
            'require_location' => false,
            'display_countdown' => true,
        ],
        Attendance::TYPE_BIOMETRIC => [
            'device_type' => 'fingerprint', // fingerprint, face, iris
            'verification_level' => 'medium', // low, medium, high
            'allow_fallback' => true,
            'fallback_method' => 'qr_static',
            'device_ids' => [], // Registered device IDs
            'api_endpoint' => null,
            'api_key' => null,
        ],
    ];

    /**
     * Get the branch relationship.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get a specific setting value with default fallback.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];
        $defaults = self::DEFAULT_SETTINGS[$this->type] ?? [];

        return $settings[$key] ?? $defaults[$key] ?? $default;
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;

        return $this;
    }

    /**
     * Get merged settings with defaults.
     */
    public function getMergedSettings(): array
    {
        $defaults = self::DEFAULT_SETTINGS[$this->type] ?? [];
        $settings = $this->settings ?? [];

        return array_merge($defaults, $settings);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter enabled settings.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to filter by branch or global (null branch).
     */
    public function scopeForBranch($query, ?string $branchId = null)
    {
        if ($branchId) {
            return $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            })->orderByRaw('branch_id IS NULL'); // Prefer branch-specific over global
        }

        return $query->whereNull('branch_id');
    }

    /**
     * Get settings for a specific type and branch.
     * Returns branch-specific settings if available, otherwise global settings.
     */
    public static function getForType(string $type, ?string $branchId = null): ?self
    {
        $query = static::query()->ofType($type)->enabled();

        if ($branchId) {
            // First try branch-specific
            $setting = $query->clone()->where('branch_id', $branchId)->first();
            if ($setting) {
                return $setting;
            }
        }

        // Fall back to global (null branch)
        return $query->whereNull('branch_id')->first();
    }

    /**
     * Get all enabled types for a branch.
     */
    /**
     * Whether a check-in method is enabled at the tenant/branch level.
     * Manual is special: ABSENCE of a row means ENABLED; every other method
     * requires an enabled settings row.
     */
    public static function isMethodEnabled(string $method, ?string $branchId = null): bool
    {
        if ($method === Attendance::TYPE_MANUAL) {
            return static::isManualEnabled($branchId);
        }

        return static::getForType($method, $branchId) !== null;
    }

    /**
     * Whether manual check-in is enabled (branch row wins over global).
     */
    public static function isManualEnabled(?string $branchId = null): bool
    {
        $query = static::query()->ofType(Attendance::TYPE_MANUAL);

        $row = null;
        if ($branchId) {
            $row = (clone $query)->where('branch_id', $branchId)->first();
        }
        $row ??= (clone $query)->whereNull('branch_id')->first();

        return $row?->is_enabled ?? true;
    }

    public static function getEnabledTypes(?string $branchId = null): array
    {
        $settings = static::query()
            ->enabled()
            ->forBranch($branchId)
            ->get()
            ->keyBy('type');

        return $settings->keys()->toArray();
    }

    /**
     * Generate QR code content for static QR.
     */
    public function generateStaticQrContent(): string
    {
        if ($this->type !== Attendance::TYPE_QR_STATIC) {
            throw new \InvalidArgumentException('This method is only for static QR settings');
        }

        $secret = $this->getSetting('qr_secret') ?: bin2hex(random_bytes(16));
        $this->setSetting('qr_secret', $secret);

        $content = json_encode([
            'type' => 'attendance_checkin',
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'secret' => $secret,
            'created_at' => now()->toIso8601String(),
        ]);

        $this->setSetting('qr_content', base64_encode($content));
        $this->save();

        return $this->getSetting('qr_content');
    }

    /**
     * Generate secret key for dynamic QR (TOTP).
     */
    public function generateDynamicQrSecret(): string
    {
        if ($this->type !== Attendance::TYPE_QR_DYNAMIC) {
            throw new \InvalidArgumentException('This method is only for dynamic QR settings');
        }

        $secret = strtoupper(bin2hex(random_bytes(20)));
        $this->setSetting('secret_key', $secret);
        $this->save();

        return $secret;
    }

    /**
     * Get current dynamic QR code (TOTP-based).
     */
    public function getCurrentDynamicCode(): ?string
    {
        if ($this->type !== Attendance::TYPE_QR_DYNAMIC) {
            return null;
        }

        $secret = $this->getSetting('secret_key');
        if (!$secret) {
            return null;
        }

        $interval = $this->getSetting('refresh_interval_seconds', 30);
        $timestamp = floor(time() / $interval);

        // Simple TOTP implementation
        $hash = hash_hmac('sha1', pack('J', $timestamp), hex2bin($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validate a dynamic QR code.
     */
    public function validateDynamicCode(string $code): bool
    {
        if ($this->type !== Attendance::TYPE_QR_DYNAMIC) {
            return false;
        }

        $secret = $this->getSetting('secret_key');
        if (!$secret) {
            return false;
        }

        $interval = $this->getSetting('refresh_interval_seconds', 30);
        $validity = $this->getSetting('validity_seconds', 60);
        $windows = ceil($validity / $interval);

        // Check current and previous windows
        for ($i = 0; $i <= $windows; $i++) {
            $timestamp = floor((time() - ($i * $interval)) / $interval);
            $hash = hash_hmac('sha1', pack('J', $timestamp), hex2bin($secret), true);
            $offset = ord($hash[19]) & 0x0F;
            $expectedCode = (
                ((ord($hash[$offset]) & 0x7F) << 24) |
                ((ord($hash[$offset + 1]) & 0xFF) << 16) |
                ((ord($hash[$offset + 2]) & 0xFF) << 8) |
                (ord($hash[$offset + 3]) & 0xFF)
            ) % 1000000;

            if ($code === str_pad($expectedCode, 6, '0', STR_PAD_LEFT)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate static QR code.
     */
    public function validateStaticQrCode(string $qrContent): bool
    {
        if ($this->type !== Attendance::TYPE_QR_STATIC) {
            return false;
        }

        $expectedContent = $this->getSetting('qr_content');
        if (!$expectedContent) {
            return false;
        }

        return hash_equals($expectedContent, $qrContent);
    }

    /**
     * Check if location is within geofence.
     */
    public function isWithinGeofence(float $latitude, float $longitude, ?float $accuracy = null): bool
    {
        if ($this->type !== Attendance::TYPE_GEOFENCE) {
            return false;
        }

        // Check accuracy requirement
        $minAccuracy = $this->getSetting('min_accuracy_meters', 50);
        if ($accuracy !== null && $accuracy > $minAccuracy && $this->getSetting('require_high_accuracy', true)) {
            return false;
        }

        $locations = $this->getSetting('locations', []);
        $defaultRadius = $this->getSetting('radius_meters', 100);

        foreach ($locations as $location) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $location['lat'],
                $location['lng']
            );

            $radius = $location['radius'] ?? $defaultRadius;
            if ($distance <= $radius) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula.
     */
    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
