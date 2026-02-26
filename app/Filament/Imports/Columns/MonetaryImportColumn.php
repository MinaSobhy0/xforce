<?php

namespace App\Filament\Imports\Columns;

use Filament\Actions\Imports\ImportColumn;

class MonetaryImportColumn extends ImportColumn
{
    protected int $multiplier = 100;

    protected bool $allowNegative = false;

    public function multiplier(int $multiplier): static
    {
        $this->multiplier = $multiplier;

        return $this;
    }

    public function allowNegative(bool $allow = true): static
    {
        $this->allowNegative = $allow;

        return $this;
    }

    public function getMultiplier(): int
    {
        return $this->multiplier;
    }

    /**
     * Mutate the state to convert from major units (e.g., 10.50) to minor units (e.g., 1050).
     */
    protected function mutateAfterFill(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return null;
        }

        // Clean the value (remove currency symbols, spaces, etc.)
        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $state);

        // Handle comma as decimal separator
        if (preg_match('/,\d{1,2}$/', $cleaned)) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        // Remove thousands separators
        $cleaned = str_replace(',', '', $cleaned);

        $value = (float) $cleaned;

        if (!$this->allowNegative && $value < 0) {
            $value = abs($value);
        }

        // Convert to minor units (cents)
        return (int) round($value * $this->multiplier);
    }

    /**
     * Create a monetary column that converts to minor units.
     */
    public static function makeMoney(string $name, ?string $label = null): static
    {
        return static::make($name)
            ->label($label)
            ->castStateUsing(function ($state) {
                if ($state === null || $state === '') {
                    return null;
                }

                // Clean the value
                $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $state);

                // Handle comma as decimal separator
                if (preg_match('/,\d{1,2}$/', $cleaned)) {
                    $cleaned = str_replace(',', '.', $cleaned);
                }

                // Remove thousands separators
                $cleaned = str_replace(',', '', $cleaned);

                $value = (float) $cleaned;

                // Convert to minor units (cents)
                return (int) round($value * 100);
            });
    }
}
