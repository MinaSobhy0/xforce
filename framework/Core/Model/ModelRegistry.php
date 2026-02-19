<?php

namespace XLinic\Framework\Core\Model;

use Illuminate\Support\Collection;
use XLinic\Framework\Core\Model\Extensions\ModelExtension;

class ModelRegistry
{
    /**
     * Registered models.
     *
     * @var array<string, string>
     */
    protected array $models = [];

    /**
     * Model extensions.
     *
     * @var array<string, array<ModelExtension>>
     */
    protected array $extensions = [];

    /**
     * Model observers.
     *
     * @var array<string, array<string>>
     */
    protected array $observers = [];

    /**
     * Register a model.
     */
    public function registerModel(string $modelClass): void
    {
        $this->models[class_basename($modelClass)] = $modelClass;
    }

    /**
     * Get a registered model by name.
     */
    public function getModel(string $name): ?string
    {
        return $this->models[$name] ?? null;
    }

    /**
     * Get all registered models.
     */
    public function getAllModels(): array
    {
        return $this->models;
    }

    /**
     * Register a model extension.
     */
    public function registerExtension(string $targetModel, ModelExtension $extension): void
    {
        if (!isset($this->extensions[$targetModel])) {
            $this->extensions[$targetModel] = [];
        }

        $this->extensions[$targetModel][] = $extension;
    }

    /**
     * Get extensions for a model.
     */
    public function getExtensions(string $modelClass): array
    {
        $modelName = class_basename($modelClass);
        return $this->extensions[$modelName] ?? [];
    }

    /**
     * Apply extensions to a model instance.
     */
    public function applyExtensions($model): void
    {
        $extensions = $this->getExtensions(get_class($model));

        foreach ($extensions as $extension) {
            $extension->apply($model);
        }
    }

    /**
     * Register a model observer.
     */
    public function registerObserver(string $modelClass, string $observerClass): void
    {
        if (!isset($this->observers[$modelClass])) {
            $this->observers[$modelClass] = [];
        }

        $this->observers[$modelClass][] = $observerClass;

        // Register the observer with Laravel
        $modelClass::observe($observerClass);
    }

    /**
     * Get observers for a model.
     */
    public function getObservers(string $modelClass): array
    {
        return $this->observers[$modelClass] ?? [];
    }

    /**
     * Register multiple models from an array.
     */
    public function registerModels(array $models): void
    {
        foreach ($models as $modelClass) {
            if (class_exists($modelClass)) {
                $this->registerModel($modelClass);
            }
        }
    }

    /**
     * Check if a model is registered.
     */
    public function hasModel(string $name): bool
    {
        return isset($this->models[$name]);
    }

    /**
     * Get model instance.
     */
    public function makeModel(string $name, array $attributes = [])
    {
        $modelClass = $this->getModel($name);

        if (!$modelClass) {
            throw new \InvalidArgumentException("Model not found: {$name}");
        }

        return new $modelClass($attributes);
    }

    /**
     * Get model relationships.
     */
    public function getModelRelationships(string $modelClass): array
    {
        $relationships = [];
        $reflection = new \ReflectionClass($modelClass);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $returnType = $method->getReturnType();

            if (!$returnType) {
                continue;
            }

            $returnTypeName = $returnType->getName();

            // Check if it's a relationship method
            if (str_starts_with($returnTypeName, 'Illuminate\Database\Eloquent\Relations')) {
                $relationships[] = [
                    'name' => $method->getName(),
                    'type' => class_basename($returnTypeName),
                ];
            }
        }

        return $relationships;
    }

    /**
     * Get model fillable attributes.
     */
    public function getModelFillable(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $instance = new $modelClass();
        return $instance->getFillable();
    }

    /**
     * Get model hidden attributes.
     */
    public function getModelHidden(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $instance = new $modelClass();
        return $instance->getHidden();
    }

    /**
     * Get model casts.
     */
    public function getModelCasts(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $instance = new $modelClass();
        return $instance->getCasts();
    }

    /**
     * Get model table name.
     */
    public function getModelTable(string $modelClass): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        $instance = new $modelClass();
        return $instance->getTable();
    }

    /**
     * Get model validation rules if defined.
     */
    public function getModelValidationRules(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        // Check if model has validation rules
        if (property_exists($modelClass, 'rules')) {
            return $modelClass::$rules ?? [];
        }

        if (method_exists($modelClass, 'rules')) {
            $instance = new $modelClass();
            return $instance->rules() ?? [];
        }

        return [];
    }

    /**
     * Discover models in a namespace.
     */
    public function discoverModels(string $namespace, string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*.php');

        foreach ($files as $file) {
            $className = $namespace . '\\' . basename($file, '.php');

            if (class_exists($className) && is_subclass_of($className, BaseModel::class)) {
                $this->registerModel($className);
            }
        }
    }

    /**
     * Get model statistics.
     */
    public function getModelStats(): array
    {
        return [
            'total_models' => count($this->models),
            'total_extensions' => array_sum(array_map('count', $this->extensions)),
            'total_observers' => array_sum(array_map('count', $this->observers)),
            'models_with_extensions' => count(array_filter($this->extensions)),
            'models_with_observers' => count(array_filter($this->observers)),
        ];
    }

    /**
     * Clear all registrations (useful for testing).
     */
    public function clear(): void
    {
        $this->models = [];
        $this->extensions = [];
        $this->observers = [];
    }

    /**
     * Export model registry data.
     */
    public function export(): array
    {
        return [
            'models' => $this->models,
            'extensions' => array_map(
                fn($extensions) => array_map(
                    fn($ext) => get_class($ext),
                    $extensions
                ),
                $this->extensions
            ),
            'observers' => $this->observers,
        ];
    }
}