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

                    // Handle schema-qualified table names
                    $table = $this->getTable();
                    if (str_contains($table, '.')) {
                        [$schema, $tableName] = explode('.', $table, 2);
                        $quotedTable = '"' . $schema . '"."' . $tableName . '"';
                    } else {
                        $quotedTable = '"' . $table . '"';
                    }

                    // Update using raw SQL for this field
                    \DB::statement(
                        "UPDATE {$quotedTable} SET {$field} = {$sqlValue} WHERE {$this->getKeyName()} = ?",
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
        $hasBooleanFields = false;

        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $this->attributes)) {
                $hasBooleanFields = true;
                break;
            }
        }

        if (!$hasBooleanFields) {
            return parent::performInsert($query);
        }

        // Fire the creating event - this allows other traits (HasSequence, etc.)
        // to set their values before we do the insert
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // Set timestamps if the model uses them
        if ($this->usesTimestamps()) {
            $time = $this->freshTimestamp();
            if (!isset($this->attributes['created_at'])) {
                $this->attributes['created_at'] = $time;
            }
            if (!isset($this->attributes['updated_at'])) {
                $this->attributes['updated_at'] = $time;
            }
        }

        // Use raw SQL insert for models with boolean fields
        $attributes = $this->getAttributes();
        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($attributes as $key => $value) {
            $columns[] = '"' . $key . '"';
            if (in_array($key, $booleanFields)) {
                // Use literal true/false for boolean columns
                $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                $placeholders[] = $boolValue ? 'true' : 'false';
            } elseif ($value instanceof \DateTimeInterface) {
                $placeholders[] = '?';
                $values[] = $value->format('Y-m-d H:i:s');
            } else {
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        // Handle schema-qualified table names (e.g., public.table_name)
        $table = $this->getTable();
        if (str_contains($table, '.')) {
            [$schema, $tableName] = explode('.', $table, 2);
            $quotedTable = '"' . $schema . '"."' . $tableName . '"';
        } else {
            $quotedTable = '"' . $table . '"';
        }

        // Use RETURNING to get the auto-generated ID
        $keyName = $this->getKeyName();
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING "%s"',
            $quotedTable,
            implode(', ', $columns),
            implode(', ', $placeholders),
            $keyName
        );

        $result = \DB::select($sql, $values);

        // Set the ID from the RETURNING clause
        if (!empty($result)) {
            $this->setAttribute($keyName, $result[0]->{$keyName});
        }

        // Set exists to true and fire created event
        $this->exists = true;
        $this->wasRecentlyCreated = true;
        $this->fireModelEvent('created', false);

        return true;
    }
}
