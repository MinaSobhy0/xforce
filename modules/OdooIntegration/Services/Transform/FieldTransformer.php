<?php

namespace Modules\OdooIntegration\Services\Transform;

use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Models\OdooFieldMapping;
use Modules\OdooIntegration\Exceptions\OdooSyncException;

class FieldTransformer
{
    public function __construct(
        protected RelationResolver $relationResolver,
        protected TimezoneConverter $timezoneConverter,
    ) {}

    /**
     * Transform Odoo data to local format.
     */
    public function transformImport(OdooEntityMapping $mapping, array $odooData): array
    {
        $localData = [];
        $fieldMappings = $mapping->getActiveFieldMappings();

        foreach ($fieldMappings as $fieldMapping) {
            if (!$fieldMapping->allowsImport()) {
                continue;
            }

            $odooField = $fieldMapping->odoo_field;
            $localField = $fieldMapping->local_field;

            // Get value from Odoo data (handle nested fields like user_id[0])
            $value = $this->getOdooValue($odooData, $odooField);

            // Apply transformation
            $transformedValue = $this->applyTransform(
                $fieldMapping,
                $value,
                'import',
                $mapping
            );

            if ($transformedValue !== null || !$fieldMapping->is_required) {
                $localData[$localField] = $transformedValue;
            } elseif ($fieldMapping->default_value !== null) {
                $localData[$localField] = $fieldMapping->default_value;
            }
        }

        return $localData;
    }

    /**
     * Transform local data to Odoo format.
     */
    public function transformExport(OdooEntityMapping $mapping, array $localData): array
    {
        $odooData = [];
        $fieldMappings = $mapping->getActiveFieldMappings();

        foreach ($fieldMappings as $fieldMapping) {
            if (!$fieldMapping->allowsExport()) {
                continue;
            }

            $localField = $fieldMapping->local_field;
            $odooField = $fieldMapping->odoo_field;

            $value = $localData[$localField] ?? null;

            // Apply transformation
            $transformedValue = $this->applyTransform(
                $fieldMapping,
                $value,
                'export',
                $mapping
            );

            if ($transformedValue !== null) {
                $odooData[$odooField] = $transformedValue;
            } elseif ($fieldMapping->default_value !== null && $fieldMapping->is_required) {
                $odooData[$odooField] = $fieldMapping->default_value;
            }
        }

        return $odooData;
    }

    /**
     * Apply transformation to a value.
     */
    protected function applyTransform(
        OdooFieldMapping $fieldMapping,
        mixed $value,
        string $direction,
        OdooEntityMapping $entityMapping
    ): mixed {
        $type = $fieldMapping->transform_type;
        $config = $fieldMapping->transform_config ?? [];

        try {
            return match ($type) {
                'direct' => $this->transformDirect($value),
                'date' => $this->transformDate($value, $direction, $config),
                'datetime' => $this->transformDatetime($value, $direction, $config, $entityMapping),
                'money' => $this->transformMoney($value, $direction, $config),
                'relation' => $this->transformRelation($value, $direction, $config, $entityMapping),
                'enum' => $this->transformEnum($value, $direction, $config),
                'boolean' => $this->transformBoolean($value, $direction),
                'json' => $this->transformJson($value, $direction),
                'translatable' => $this->transformTranslatable($value, $direction, $config),
                'split_name' => $this->transformSplitName($value, $direction, $config),
                'many2many' => $this->transformMany2Many($value, $direction, $config),
                'percentage' => $this->transformPercentage($value, $direction),
                default => $value,
            };
        } catch (\Exception $e) {
            throw OdooSyncException::transformFailed($fieldMapping->local_field, $e->getMessage());
        }
    }

    /**
     * Get value from Odoo data, handling nested fields.
     */
    protected function getOdooValue(array $data, string $field): mixed
    {
        // Handle many2one fields which return [id, name] arrays
        if (isset($data[$field]) && is_array($data[$field]) && count($data[$field]) === 2) {
            // Return the ID for relation fields
            return $data[$field][0];
        }

        return $data[$field] ?? null;
    }

    /**
     * Direct copy transformation.
     */
    protected function transformDirect(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Date transformation.
     */
    protected function transformDate(mixed $value, string $direction, array $config): ?string
    {
        if (empty($value) || $value === false) {
            return null;
        }

        if ($direction === 'import') {
            // Odoo date format: YYYY-MM-DD
            return $value;
        }

        // Export: ensure proper format
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    /**
     * DateTime transformation with timezone conversion.
     */
    protected function transformDatetime(
        mixed $value,
        string $direction,
        array $config,
        OdooEntityMapping $entityMapping
    ): ?string {
        if (empty($value) || $value === false) {
            return null;
        }

        $odooTz = $entityMapping->connection->timezone ?? 'UTC';
        $localTz = config('app.timezone', 'Africa/Cairo');

        if ($direction === 'import') {
            return $this->timezoneConverter->convert($value, $odooTz, $localTz);
        }

        return $this->timezoneConverter->convert($value, $localTz, $odooTz);
    }

    /**
     * Money transformation (cents <-> decimal).
     */
    protected function transformMoney(mixed $value, string $direction, array $config): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decimals = $config['decimals'] ?? 2;

        if ($direction === 'import') {
            // Odoo uses decimal, local uses minor units (cents)
            return (int) round(floatval($value) * pow(10, $decimals));
        }

        // Export: convert cents to decimal
        return round(intval($value) / pow(10, $decimals), $decimals);
    }

    /**
     * Relation transformation (resolve odoo_id <-> local_id).
     */
    protected function transformRelation(
        mixed $value,
        string $direction,
        array $config,
        OdooEntityMapping $entityMapping
    ): mixed {
        if (empty($value) || $value === false) {
            return null;
        }

        $relatedModel = $config['model'] ?? null;
        $relatedOdooModel = $config['odoo_model'] ?? null;

        if ($direction === 'import') {
            // Resolve Odoo ID to local ID
            return $this->relationResolver->resolveToLocalId(
                $value,
                $relatedModel,
                $relatedOdooModel,
                $entityMapping->odoo_connection_id
            );
        }

        // Resolve local ID to Odoo ID
        return $this->relationResolver->resolveToOdooId(
            $value,
            $relatedModel,
            $entityMapping->odoo_connection_id
        );
    }

    /**
     * Enum transformation (value mapping).
     */
    protected function transformEnum(mixed $value, string $direction, array $config): mixed
    {
        if ($value === null) {
            return null;
        }

        $mapping = $config['mapping'] ?? [];

        if ($direction === 'import') {
            // Odoo value to local value
            return $mapping[$value] ?? $config['default'] ?? $value;
        }

        // Local value to Odoo value
        $reverseMapping = array_flip($mapping);
        return $reverseMapping[$value] ?? $config['default'] ?? $value;
    }

    /**
     * Boolean transformation.
     */
    protected function transformBoolean(mixed $value, string $direction): bool
    {
        if ($direction === 'import') {
            // Odoo uses Python True/False which becomes true/false in JSON
            return (bool) $value;
        }

        return (bool) $value;
    }

    /**
     * JSON transformation.
     */
    protected function transformJson(mixed $value, string $direction): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($direction === 'import') {
            // Odoo might send JSON string or array
            if (is_string($value)) {
                return json_decode($value, true);
            }
            return $value;
        }

        // Export: encode as JSON string if Odoo expects string
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }

    /**
     * Translatable field transformation (JSONB {en, ar}).
     */
    protected function transformTranslatable(mixed $value, string $direction, array $config): mixed
    {
        if ($value === null || $value === false) {
            return null;
        }

        $defaultLocale = $config['default_locale'] ?? 'en';

        if ($direction === 'import') {
            // Odoo sends single value, convert to JSONB
            if (is_string($value)) {
                return [$defaultLocale => $value];
            }
            return $value;
        }

        // Export: get value from JSONB
        if (is_array($value)) {
            return $value[$defaultLocale] ?? $value['en'] ?? reset($value);
        }
        return $value;
    }

    /**
     * Split name transformation.
     */
    protected function transformSplitName(mixed $value, string $direction, array $config): mixed
    {
        $part = $config['part'] ?? 'first';

        if ($direction === 'import') {
            // Split full name into parts
            if (!is_string($value) || empty($value)) {
                return null;
            }

            $parts = explode(' ', trim($value), 2);

            return match ($part) {
                'first' => $parts[0] ?? null,
                'last' => $parts[1] ?? null,
                default => $value,
            };
        }

        // Export: this is typically not used for export
        // as we'd combine fields differently
        return $value;
    }

    /**
     * Many2Many transformation.
     */
    protected function transformMany2Many(mixed $value, string $direction, array $config): mixed
    {
        if ($value === null) {
            return [];
        }

        if ($direction === 'import') {
            // Odoo sends array of IDs
            // This would need to be handled specially in the import process
            return is_array($value) ? $value : [$value];
        }

        // Export: Odoo expects [(6, 0, [ids])] for replace
        if (is_array($value)) {
            return [[6, 0, $value]];
        }
        return [[6, 0, []]];
    }

    /**
     * Percentage transformation.
     */
    protected function transformPercentage(mixed $value, string $direction): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($direction === 'import') {
            // Odoo might send 50 for 50% or 0.5 for 50%
            // Assume whole number percentage
            return floatval($value);
        }

        return floatval($value);
    }
}
