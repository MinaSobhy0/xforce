<?php

namespace Modules\PatientPortal\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Patients\Models\Patient;

class PortalSession extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'token',
        'otp_code',
        'otp_expires_at',
        'verified_at',
        'ip_address',
        'user_agent',
        'device_type',
        'last_activity_at',
        'expires_at',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
        'otp_code',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // Check if session is verified
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    // Check if session is expired
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // Check if OTP is expired
    public function isOtpExpired(): bool
    {
        return $this->otp_expires_at && $this->otp_expires_at->isPast();
    }

    // Check if session is active
    public function isActive(): bool
    {
        return $this->isVerified() && !$this->isExpired();
    }

    // Update last activity
    public function touch(): bool
    {
        $this->last_activity_at = now();
        return $this->save();
    }

    // Generate new OTP
    public function generateOtp(int $length = 6): string
    {
        $otp = str_pad((string) random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
        $this->otp_code = $otp;
        $this->otp_expires_at = now()->addMinutes(5);
        $this->save();

        return $otp;
    }

    // Verify OTP
    // SECURITY: Uses timing-safe comparison to prevent timing attacks
    public function verifyOtp(string $code): bool
    {
        if ($this->isOtpExpired()) {
            return false;
        }

        // SECURITY: Use hash_equals() for timing-safe string comparison
        // This prevents attackers from guessing OTP digit-by-digit via timing
        if (!hash_equals((string) $this->otp_code, (string) $code)) {
            return false;
        }

        $this->verified_at = now();
        $this->otp_code = null;
        $this->otp_expires_at = null;
        $this->save();

        return true;
    }

    // Scope for active sessions
    public function scopeActive($query)
    {
        return $query->whereNotNull('verified_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    // Scope for patient
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }
}
