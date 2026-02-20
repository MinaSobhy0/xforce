<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BaseConnection;
use PDO;

class PostgresConnection extends BaseConnection
{
    /**
     * Bind values to their parameters in the given statement.
     *
     * Override to properly handle PostgreSQL boolean types.
     * PostgreSQL requires PDO::PARAM_BOOL for boolean columns.
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $paramKey = is_string($key) ? $key : $key + 1;

            if (is_bool($value)) {
                $statement->bindValue($paramKey, $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $statement->bindValue($paramKey, $value, PDO::PARAM_INT);
            } elseif (is_null($value)) {
                $statement->bindValue($paramKey, $value, PDO::PARAM_NULL);
            } else {
                $statement->bindValue($paramKey, $value, PDO::PARAM_STR);
            }
        }
    }

    /**
     * Prepare the query bindings for execution.
     *
     * IMPORTANT: Override to NOT convert booleans to integers.
     * Laravel's default implementation does `(int) $value` for booleans,
     * which breaks PostgreSQL's strict type checking.
     * PostgreSQL requires actual boolean values or PDO::PARAM_BOOL.
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            // KEEP booleans as actual booleans for PostgreSQL
            // DO NOT convert to integers like the parent class does
            if (is_bool($value)) {
                // Keep as boolean - will be bound with PDO::PARAM_BOOL
                continue;
            }

            // Date handling
            if ($value instanceof \DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            }
        }

        return $bindings;
    }
}
