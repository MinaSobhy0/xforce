<?php

namespace App\Traits;

/**
 * Trait SanitizesPdfData
 *
 * SECURITY: Provides sanitization methods for data rendered in PDFs.
 * Even though Blade's {{ }} syntax escapes HTML, we add additional
 * protection to prevent HTML injection that could affect DomPDF rendering.
 */
trait SanitizesPdfData
{
    /**
     * Sanitize a string for safe PDF rendering.
     * Removes HTML tags and dangerous characters.
     */
    protected function sanitizeForPdf(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Strip all HTML tags
        $sanitized = strip_tags($value);

        // Remove potential CSS expression attacks
        $sanitized = preg_replace('/expression\s*\([^)]*\)/i', '', $sanitized);

        // Remove null bytes and other control characters (except newlines/tabs)
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $sanitized);

        // Limit length to prevent DoS via oversized text
        if (mb_strlen($sanitized) > 10000) {
            $sanitized = mb_substr($sanitized, 0, 10000) . '...';
        }

        return $sanitized;
    }

    /**
     * Sanitize an array of data recursively for PDF rendering.
     */
    protected function sanitizeArrayForPdf(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeForPdf($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArrayForPdf($value);
            } elseif ($value instanceof \Illuminate\Support\Collection) {
                $sanitized[$key] = $value->map(fn ($item) =>
                    is_array($item) ? $this->sanitizeArrayForPdf($item) : $item
                );
            } else {
                // Keep non-string values as-is (numbers, booleans, objects)
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize patient/user data specifically.
     * These fields are particularly at risk as they contain user input.
     */
    protected function sanitizeUserDataForPdf(array $userData): array
    {
        $fieldsToSanitize = [
            'name', 'full_name', 'address', 'email', 'phone',
            'notes', 'description', 'instructions', 'special_instructions',
            'diagnosis', 'reference', 'title', 'specialty', 'license',
            'license_number', 'code', 'prescription_number', 'footer',
        ];

        foreach ($fieldsToSanitize as $field) {
            if (isset($userData[$field]) && is_string($userData[$field])) {
                $userData[$field] = $this->sanitizeForPdf($userData[$field]);
            }
        }

        return $userData;
    }
}
