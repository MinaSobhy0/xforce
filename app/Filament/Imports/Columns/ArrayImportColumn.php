<?php

namespace App\Filament\Imports\Columns;

use Filament\Actions\Imports\ImportColumn;

class ArrayImportColumn extends ImportColumn
{
    protected string $delimiter = ',';

    protected bool $trimValues = true;

    protected bool $filterEmpty = true;

    protected bool $unique = false;

    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    public function trimValues(bool $trim = true): static
    {
        $this->trimValues = $trim;

        return $this;
    }

    public function filterEmpty(bool $filter = true): static
    {
        $this->filterEmpty = $filter;

        return $this;
    }

    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;

        return $this;
    }

    /**
     * Parse the value into an array.
     */
    public function parseArray(mixed $state): ?array
    {
        if ($state === null || $state === '') {
            return null;
        }

        // If already an array, return it
        if (is_array($state)) {
            return $this->processArray($state);
        }

        $state = (string) $state;

        // Try to parse as JSON first
        if ($this->isJson($state)) {
            $decoded = json_decode($state, true);
            if (is_array($decoded)) {
                return $this->processArray($decoded);
            }
        }

        // Parse as delimited string
        $values = explode($this->delimiter, $state);

        return $this->processArray($values);
    }

    protected function processArray(array $values): array
    {
        if ($this->trimValues) {
            $values = array_map('trim', $values);
        }

        if ($this->filterEmpty) {
            $values = array_filter($values, fn($v) => $v !== '' && $v !== null);
        }

        if ($this->unique) {
            $values = array_unique($values);
        }

        return array_values($values);
    }

    protected function isJson(string $value): bool
    {
        if (!str_starts_with($value, '[') && !str_starts_with($value, '{')) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Create an array column with common defaults.
     */
    public static function makeArray(string $name, string $delimiter = ','): static
    {
        return static::make($name)
            ->delimiter($delimiter)
            ->castStateUsing(function ($state) use ($delimiter) {
                if ($state === null || $state === '') {
                    return null;
                }

                if (is_array($state)) {
                    return array_values(array_filter(array_map('trim', $state)));
                }

                $state = (string) $state;

                // Try JSON first
                if ((str_starts_with($state, '[') || str_starts_with($state, '{')) && json_decode($state) !== null) {
                    return json_decode($state, true);
                }

                // Parse delimited string
                return array_values(array_filter(array_map('trim', explode($delimiter, $state))));
            });
    }
}
