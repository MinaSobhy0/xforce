<?php

namespace Modules\GiftCards\Services;

use Illuminate\Support\Facades\Crypt;

class GiftCardGeneratorService
{
    /**
     * Generate secure gift card serial number
     * Format: 12 digits + 2 check digits = XXXX-XXXX-XXXX-XX
     */
    public function generateSerialNumber(): string
    {
        // Generate 12 random digits
        $digits = '';
        for ($i = 0; $i < 12; $i++) {
            $digits .= random_int(0, 9);
        }

        // Calculate 2 Luhn check digits
        $checkDigits = $this->calculateLuhnCheckDigits($digits);

        return $digits . $checkDigits;
    }

    /**
     * Format card number for display
     */
    public function formatCardNumber(string $serial): string
    {
        $clean = preg_replace('/[^0-9]/', '', $serial);

        // XXXX-XXXX-XXXX-XX
        return sprintf(
            '%s-%s-%s-%s',
            substr($clean, 0, 4),
            substr($clean, 4, 4),
            substr($clean, 8, 4),
            substr($clean, 12, 2)
        );
    }

    /**
     * Validate card number using Luhn algorithm
     */
    public function validateCardNumber(string $serial): bool
    {
        $serial = preg_replace('/[^0-9]/', '', $serial);

        if (strlen($serial) !== 14) {
            return false;
        }

        $digits = substr($serial, 0, 12);
        $checkDigits = substr($serial, 12, 2);

        return $checkDigits === $this->calculateLuhnCheckDigits($digits);
    }

    /**
     * Generate optional PIN code
     */
    public function generatePinCode(int $length = 4): string
    {
        $pin = '';
        for ($i = 0; $i < $length; $i++) {
            $pin .= random_int(0, 9);
        }
        return $pin;
    }

    /**
     * Encrypt serial code for storage
     */
    public function encryptCode(string $code): string
    {
        return Crypt::encryptString($code);
    }

    /**
     * Decrypt serial code
     */
    public function decryptCode(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    /**
     * Generate hash for lookup
     */
    public function hashCode(string $code): string
    {
        $clean = preg_replace('/[^0-9]/', '', $code);
        return hash('sha256', $clean);
    }

    /**
     * Calculate Luhn check digits
     */
    protected function calculateLuhnCheckDigits(string $digits): string
    {
        // Standard Luhn algorithm
        $sum = 0;
        $length = strlen($digits);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        $checkDigit1 = (10 - ($sum % 10)) % 10;

        // Second check digit based on modified sum
        $sum2 = $sum + $checkDigit1;
        $checkDigit2 = (10 - ($sum2 % 10)) % 10;

        return $checkDigit1 . $checkDigit2;
    }

    /**
     * Parse a card code input (removes formatting)
     */
    public function parseCardCode(string $input): string
    {
        return preg_replace('/[^0-9]/', '', $input);
    }
}
