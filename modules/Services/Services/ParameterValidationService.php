<?php

namespace Modules\Services\Services;

use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceParameter;
use Modules\Services\Models\ParameterTemplate;

class ParameterValidationService
{
    /**
     * Validate parameter values for a service.
     */
    public function validateForService(Service $service, array $values): ValidationResult
    {
        $errors = [];
        $sanitized = [];
        $definitions = $service->getParameterDefinitions();

        foreach ($definitions as $definition) {
            $key = $definition['key'] ?? null;
            if (!$key) {
                continue;
            }

            $value = $values[$key] ?? null;
            $isRequired = $definition['required'] ?? false;

            // Check required
            if ($isRequired && $this->isEmpty($value)) {
                $errors[$key] = $this->getErrorMessage('required', $definition);
                continue;
            }

            // Skip validation if empty and not required
            if ($this->isEmpty($value)) {
                continue;
            }

            // Validate and sanitize based on type
            $type = $definition['type'] ?? 'text';
            $result = $this->validateAndSanitize($value, $definition);

            if ($result['error']) {
                $errors[$key] = $result['error'];
            } else {
                $sanitized[$key] = $result['value'];
            }
        }

        return new ValidationResult(
            isValid: empty($errors),
            errors: $errors,
            sanitizedValues: $sanitized
        );
    }

    /**
     * Validate parameter values against a template.
     */
    public function validateForTemplate(ParameterTemplate $template, array $values): ValidationResult
    {
        $errors = $template->validateValues($values);

        return new ValidationResult(
            isValid: empty($errors),
            errors: $errors,
            sanitizedValues: empty($errors) ? $values : []
        );
    }

    /**
     * Validate a single parameter value.
     */
    public function validateParameter(ServiceParameter $parameter, $value): ?string
    {
        return $parameter->validateValue($value);
    }

    /**
     * Validate and sanitize a value based on its definition.
     */
    protected function validateAndSanitize($value, array $definition): array
    {
        $type = $definition['type'] ?? 'text';

        switch ($type) {
            case 'number':
                return $this->validateNumber($value, $definition);

            case 'decimal':
                return $this->validateDecimal($value, $definition);

            case 'text':
            case 'textarea':
                return $this->validateText($value, $definition);

            case 'select':
                return $this->validateSelect($value, $definition);

            case 'boolean':
                return $this->validateBoolean($value);

            case 'date':
                return $this->validateDate($value, $definition);

            case 'time':
                return $this->validateTime($value, $definition);

            case 'range':
                return $this->validateRange($value, $definition);

            default:
                return ['value' => $value, 'error' => null];
        }
    }

    /**
     * Validate number type.
     */
    protected function validateNumber($value, array $definition): array
    {
        if (!is_numeric($value)) {
            return ['value' => null, 'error' => $this->getErrorMessage('not_numeric', $definition)];
        }

        $numValue = (int) $value;

        if (isset($definition['min']) && $numValue < $definition['min']) {
            return ['value' => null, 'error' => $this->getErrorMessage('min', $definition)];
        }

        if (isset($definition['max']) && $numValue > $definition['max']) {
            return ['value' => null, 'error' => $this->getErrorMessage('max', $definition)];
        }

        return ['value' => $numValue, 'error' => null];
    }

    /**
     * Validate decimal type.
     */
    protected function validateDecimal($value, array $definition): array
    {
        if (!is_numeric($value)) {
            return ['value' => null, 'error' => $this->getErrorMessage('not_numeric', $definition)];
        }

        $numValue = (float) $value;

        if (isset($definition['min']) && $numValue < $definition['min']) {
            return ['value' => null, 'error' => $this->getErrorMessage('min', $definition)];
        }

        if (isset($definition['max']) && $numValue > $definition['max']) {
            return ['value' => null, 'error' => $this->getErrorMessage('max', $definition)];
        }

        // Round to specified precision if step is defined
        if (isset($definition['step'])) {
            $precision = strlen(substr(strrchr((string) $definition['step'], "."), 1));
            $numValue = round($numValue, $precision);
        }

        return ['value' => $numValue, 'error' => null];
    }

    /**
     * Validate text type.
     */
    protected function validateText($value, array $definition): array
    {
        $strValue = trim((string) $value);
        $validation = $definition['validation'] ?? [];

        if (isset($validation['min_length']) && mb_strlen($strValue) < $validation['min_length']) {
            return ['value' => null, 'error' => "Must be at least {$validation['min_length']} characters."];
        }

        if (isset($validation['max_length']) && mb_strlen($strValue) > $validation['max_length']) {
            return ['value' => null, 'error' => "Must be at most {$validation['max_length']} characters."];
        }

        if (isset($validation['pattern']) && !preg_match($validation['pattern'], $strValue)) {
            return ['value' => null, 'error' => $this->getErrorMessage('pattern', $definition)];
        }

        return ['value' => $strValue, 'error' => null];
    }

    /**
     * Validate select type.
     */
    protected function validateSelect($value, array $definition): array
    {
        $options = array_column($definition['options'] ?? [], 'value');

        if (!in_array($value, $options)) {
            return ['value' => null, 'error' => $this->getErrorMessage('invalid_option', $definition)];
        }

        return ['value' => $value, 'error' => null];
    }

    /**
     * Validate boolean type.
     */
    protected function validateBoolean($value): array
    {
        if (is_bool($value)) {
            return ['value' => $value, 'error' => null];
        }

        if (in_array($value, [1, '1', 'true', 'yes', 'on'], true)) {
            return ['value' => true, 'error' => null];
        }

        if (in_array($value, [0, '0', 'false', 'no', 'off'], true)) {
            return ['value' => false, 'error' => null];
        }

        return ['value' => null, 'error' => 'Must be true or false.'];
    }

    /**
     * Validate date type.
     */
    protected function validateDate($value, array $definition): array
    {
        try {
            $date = \Carbon\Carbon::parse($value);
            return ['value' => $date->format('Y-m-d'), 'error' => null];
        } catch (\Exception $e) {
            return ['value' => null, 'error' => 'Invalid date format.'];
        }
    }

    /**
     * Validate time type.
     */
    protected function validateTime($value, array $definition): array
    {
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $value)) {
            return ['value' => $value, 'error' => null];
        }

        return ['value' => null, 'error' => 'Invalid time format. Use HH:MM or HH:MM:SS.'];
    }

    /**
     * Validate range type.
     */
    protected function validateRange($value, array $definition): array
    {
        if (!is_array($value) || !isset($value['min'], $value['max'])) {
            return ['value' => null, 'error' => 'Range must have min and max values.'];
        }

        if (!is_numeric($value['min']) || !is_numeric($value['max'])) {
            return ['value' => null, 'error' => 'Range values must be numeric.'];
        }

        if ($value['min'] > $value['max']) {
            return ['value' => null, 'error' => 'Minimum cannot be greater than maximum.'];
        }

        return ['value' => $value, 'error' => null];
    }

    /**
     * Check if a value is empty.
     */
    protected function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * Get localized error message.
     */
    protected function getErrorMessage(string $type, array $definition): string
    {
        $label = $this->getLabel($definition);

        return match ($type) {
            'required' => __('validation.required', ['attribute' => $label]),
            'not_numeric' => __('validation.numeric', ['attribute' => $label]),
            'min' => __('validation.min.numeric', ['attribute' => $label, 'min' => $definition['min'] ?? 0]),
            'max' => __('validation.max.numeric', ['attribute' => $label, 'max' => $definition['max'] ?? 0]),
            'invalid_option' => __('validation.in', ['attribute' => $label]),
            'pattern' => __('validation.regex', ['attribute' => $label]),
            default => __('validation.invalid', ['attribute' => $label]),
        };
    }

    /**
     * Get the label for error messages.
     */
    protected function getLabel(array $definition): string
    {
        $label = $definition['label'] ?? [];
        $locale = app()->getLocale();

        return $label[$locale] ?? $label['en'] ?? $definition['key'] ?? 'field';
    }
}

/**
 * Value object for validation results.
 */
class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors,
        public readonly array $sanitizedValues
    ) {}

    public function hasError(string $key): bool
    {
        return isset($this->errors[$key]);
    }

    public function getError(string $key): ?string
    {
        return $this->errors[$key] ?? null;
    }

    public function getValue(string $key)
    {
        return $this->sanitizedValues[$key] ?? null;
    }
}
