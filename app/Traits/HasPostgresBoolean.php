<?php

namespace App\Traits;

trait HasPostgresBoolean
{
    /**
     * Get the boolean fields that need PostgreSQL-specific handling.
     * Override this in your model to specify the fields.
     */
    protected function getPostgresBooleanFields(): array
    {
        return [];
    }

    /**
     * Override the performUpdate method to handle PostgreSQL booleans.
     */
    protected function performUpdate(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        $dirty = $this->getDirty();
        $booleanFields = $this->getPostgresBooleanFields();

        // Check if any boolean fields are being updated
        $booleanUpdates = array_intersect_key($dirty, array_flip($booleanFields));

        if (!empty($booleanUpdates)) {
            // Handle boolean fields separately with raw SQL
            foreach ($booleanFields as $field) {
                if (array_key_exists($field, $dirty)) {
                    $value = filter_var($dirty[$field], FILTER_VALIDATE_BOOLEAN);
                    $sqlValue = $value ? 'true' : 'false';

                    // Update using raw SQL for this field
                    \DB::statement(
                        "UPDATE {$this->getTable()} SET {$field} = {$sqlValue} WHERE {$this->getKeyName()} = ?",
                        [$this->getKey()]
                    );

                    // Set the attribute to the boolean value and sync to original
                    // so it's no longer considered dirty
                    $this->attributes[$field] = $value;
                    $this->original[$field] = $value;
                }
            }

            // Get remaining dirty attributes (recalculate after syncing)
            $remainingDirty = $this->getDirty();

            if (empty($remainingDirty)) {
                // All updates were boolean fields, handled above
                $this->fireModelEvent('updated', false);
                return true;
            }
        }

        return parent::performUpdate($query);
    }

    /**
     * Override the performInsert method to handle PostgreSQL booleans.
     */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        $booleanFields = $this->getPostgresBooleanFields();

        foreach ($booleanFields as $field) {
            if (isset($this->attributes[$field])) {
                $this->attributes[$field] = filter_var($this->attributes[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return parent::performInsert($query);
    }
}
