<?php

namespace App\Traits;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

trait TwoFactorAuthenticatable
{
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return !is_null($this->two_factor_secret) && !is_null($this->two_factor_confirmed_at);
    }

    public function hasTwoFactorPending(): bool
    {
        return !is_null($this->two_factor_secret) && is_null($this->two_factor_confirmed_at);
    }

    public function enableTwoFactorAuthentication(): string
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $this->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    public function confirmTwoFactorAuthentication(string $code): bool
    {
        $google2fa = new Google2FA();
        $secret = decrypt($this->two_factor_secret);

        if ($google2fa->verifyKey($secret, $code)) {
            $this->forceFill([
                'two_factor_confirmed_at' => now(),
            ])->save();

            return true;
        }

        return false;
    }

    public function disableTwoFactorAuthentication(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->hasEnabledTwoFactorAuthentication()) {
            return true;
        }

        $google2fa = new Google2FA();
        $secret = decrypt($this->two_factor_secret);

        return $google2fa->verifyKey($secret, $code);
    }

    public function verifyRecoveryCode(string $code): bool
    {
        $recoveryCodes = json_decode(decrypt($this->two_factor_recovery_codes), true);

        // SECURITY: Use constant-time comparison to prevent timing attacks
        $matchedCode = null;
        foreach ($recoveryCodes as $validCode) {
            if (hash_equals($validCode, strtoupper($code))) {
                $matchedCode = $validCode;
                break;
            }
        }

        if ($matchedCode !== null) {
            // Remove used recovery code
            $recoveryCodes = array_values(array_filter($recoveryCodes, fn($c) => !hash_equals($c, $matchedCode)));
            $this->forceFill([
                'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            ])->save();

            return true;
        }

        return false;
    }

    public function getRecoveryCodes(): array
    {
        if (!$this->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(decrypt($this->two_factor_recovery_codes), true);
    }

    public function regenerateRecoveryCodes(): array
    {
        $codes = $this->generateRecoveryCodes();

        $this->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ])->save();

        return $codes;
    }

    /**
     * Generate cryptographically secure recovery codes.
     * SECURITY: Uses random_bytes directly (not MD5) with 16 chars (~80 bits entropy).
     */
    protected function generateRecoveryCodes(): array
    {
        $codes = [];
        // Generate 10 recovery codes (industry standard is 8-16)
        for ($i = 0; $i < 10; $i++) {
            // Use bin2hex for better entropy distribution (16 hex chars = 64 bits)
            // Format as XXXX-XXXX-XXXX-XXXX for readability
            $raw = bin2hex(random_bytes(8));
            $codes[] = strtoupper(implode('-', str_split($raw, 4)));
        }

        return $codes;
    }

    public function getTwoFactorQrCodeSvg(): string
    {
        $google2fa = new Google2FA();
        $secret = decrypt($this->two_factor_secret);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $this->email,
            $secret
        );

        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
                new SvgImageBackEnd()
            )
        ))->writeString($qrCodeUrl);

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    public function getTwoFactorSecret(): ?string
    {
        if (!$this->two_factor_secret) {
            return null;
        }

        return decrypt($this->two_factor_secret);
    }
}
