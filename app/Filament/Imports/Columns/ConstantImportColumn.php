<?php

namespace App\Filament\Imports\Columns;

use Filament\Actions\Imports\ImportColumn;

class ConstantImportColumn extends ImportColumn
{
    protected array $constants = [];

    protected bool $caseSensitive = false;

    protected mixed $defaultValue = null;

    public function constants(array $constants): static
    {
        $this->constants = $constants;

        return $this;
    }

    public function caseSensitive(bool $sensitive = true): static
    {
        $this->caseSensitive = $sensitive;

        return $this;
    }

    public function defaultValue(mixed $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    public function getConstants(): array
    {
        return $this->constants;
    }

    /**
     * Resolve the constant value from the given input.
     * Accepts either the key or the label.
     */
    public function resolveConstant(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return $this->defaultValue;
        }

        $state = (string) $state;
        $compareState = $this->caseSensitive ? $state : strtolower($state);

        // First, check if it's a direct key match
        foreach ($this->constants as $key => $label) {
            $compareKey = $this->caseSensitive ? $key : strtolower($key);
            if ($compareKey === $compareState) {
                return $key;
            }
        }

        // Then, check if it matches a label
        foreach ($this->constants as $key => $label) {
            $compareLabel = $this->caseSensitive ? $label : strtolower($label);
            if ($compareLabel === $compareState) {
                return $key;
            }
        }

        // If no match found, return the default value or the original state
        return $this->defaultValue ?? $state;
    }

    /**
     * Validate that the value is a valid constant.
     */
    public function isValidConstant(mixed $state): bool
    {
        if ($state === null || $state === '') {
            return true;
        }

        $resolved = $this->resolveConstant($state);

        return array_key_exists($resolved, $this->constants);
    }

    /**
     * Get available options for mapping UI.
     */
    public function getOptions(): array
    {
        return $this->constants;
    }

    /**
     * Create a constant column from a model's constants array.
     */
    public static function makeFromModel(string $name, string $modelClass, string $constantsProperty): static
    {
        $constants = [];

        if (defined("{$modelClass}::{$constantsProperty}")) {
            $constants = constant("{$modelClass}::{$constantsProperty}");
        } elseif (property_exists($modelClass, $constantsProperty)) {
            $constants = (new $modelClass)->{$constantsProperty};
        }

        return static::make($name)->constants($constants);
    }
}
