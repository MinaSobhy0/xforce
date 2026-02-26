<?php

namespace App\Filament\Imports\Columns;

use Closure;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RelationshipImportColumn extends ImportColumn
{
    protected string $relationshipModel;

    protected array $searchColumns = ['name', 'code'];

    protected ?string $tenantColumn = 'tenant_id';

    protected bool $isTranslatable = false;

    protected array $translatableLanguages = ['en', 'ar'];

    protected bool $createIfNotFound = false;

    protected ?Closure $createUsing = null;

    public function relationship(string $model): static
    {
        $this->relationshipModel = $model;

        return $this;
    }

    public function searchColumns(array $columns): static
    {
        $this->searchColumns = $columns;

        return $this;
    }

    public function tenantColumn(?string $column): static
    {
        $this->tenantColumn = $column;

        return $this;
    }

    public function translatable(bool $translatable = true, array $languages = ['en', 'ar']): static
    {
        $this->isTranslatable = $translatable;
        $this->translatableLanguages = $languages;

        return $this;
    }

    public function createIfNotFound(bool $create = true): static
    {
        $this->createIfNotFound = $create;

        return $this;
    }

    public function createUsing(?Closure $callback): static
    {
        $this->createUsing = $callback;
        $this->createIfNotFound = true;

        return $this;
    }

    /**
     * Resolve the relationship ID from the given value.
     */
    public function resolveRelationship(mixed $state, ?string $tenantId = null): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        // If it looks like a UUID, try to find by ID first
        if ($this->isUuid($state)) {
            $record = $this->findById($state, $tenantId);
            if ($record) {
                return $record->id;
            }
        }

        // Search by configured columns
        $record = $this->findBySearchColumns($state, $tenantId);

        if ($record) {
            return $record->id;
        }

        // Create if not found and configured to do so
        if ($this->createIfNotFound) {
            $record = $this->createRecord($state, $tenantId);
            if ($record) {
                return $record->id;
            }
        }

        return null;
    }

    protected function isUuid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    protected function findById(string $id, ?string $tenantId): ?Model
    {
        $query = $this->relationshipModel::query();

        if ($this->tenantColumn && $tenantId) {
            $query->where($this->tenantColumn, $tenantId);
        }

        return $query->find($id);
    }

    protected function findBySearchColumns(mixed $value, ?string $tenantId): ?Model
    {
        $cacheKey = $this->getCacheKey($value, $tenantId);

        return Cache::remember($cacheKey, 60, function () use ($value, $tenantId) {
            $query = $this->relationshipModel::query();

            if ($this->tenantColumn && $tenantId) {
                $query->where($this->tenantColumn, $tenantId);
            }

            $query->where(function ($q) use ($value) {
                foreach ($this->searchColumns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';

                    if ($this->isTranslatable && in_array($column, ['name', 'title', 'label'])) {
                        // Search in all configured languages for translatable fields
                        $q->{$method}(function ($subQ) use ($column, $value) {
                            foreach ($this->translatableLanguages as $langIndex => $lang) {
                                $subMethod = $langIndex === 0 ? 'whereRaw' : 'orWhereRaw';
                                $subQ->{$subMethod}("{$column}->>'$lang' ILIKE ?", ["%{$value}%"]);
                            }
                        });
                    } else {
                        $q->{$method}($column, 'ILIKE', "%{$value}%");
                    }
                }
            });

            return $query->first();
        });
    }

    protected function createRecord(mixed $value, ?string $tenantId): ?Model
    {
        if ($this->createUsing) {
            return ($this->createUsing)($value, $tenantId);
        }

        // Default creation logic
        $data = [];

        if ($this->tenantColumn && $tenantId) {
            $data[$this->tenantColumn] = $tenantId;
        }

        // Use first search column for the value
        $column = $this->searchColumns[0] ?? 'name';
        if ($this->isTranslatable && in_array($column, ['name', 'title', 'label'])) {
            $data[$column] = ['en' => $value];
        } else {
            $data[$column] = $value;
        }

        return $this->relationshipModel::create($data);
    }

    protected function getCacheKey(mixed $value, ?string $tenantId): string
    {
        return sprintf(
            'import_relation_%s_%s_%s',
            md5($this->relationshipModel),
            $tenantId ?? 'global',
            md5((string) $value)
        );
    }

    /**
     * Create a relationship column with common defaults.
     */
    public static function makeRelation(string $name, string $model, array $searchColumns = ['name', 'code']): static
    {
        return static::make($name)
            ->relationship($model)
            ->searchColumns($searchColumns);
    }
}
