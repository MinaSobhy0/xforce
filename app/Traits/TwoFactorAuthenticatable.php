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

        if (in_array($code, $recoveryCodes)) {
            // Remove used recovery code
            $recoveryCodes = array_diff($recoveryCodes, [$code]);
            $this->forceFill([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
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

    protected function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(md5(random_bytes(16)), 0, 10));
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
