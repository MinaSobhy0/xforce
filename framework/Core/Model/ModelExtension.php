<?php

namespace XLinic\Framework\Core\Model;

abstract class ModelExtension
{
    /**
     * The target model class this extension applies to.
     */
    protected string $target;

    /**
     * Apply the extension to the model instance.
     */
    abstract public function apply($model): void;

    /**
     * Get the target model class.
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * Set the target model class.
     */
    public function setTarget(string $target): void
    {
        $this->target = $target;
    }

    /**
     * Get relationships to add to the model.
     */
    public function relationships(): array
    {
        return [];
    }

    /**
     * Get scopes to add to the model.
     */
    public function scopes(): array
    {
        return [];
    }

    /**
     * Get attributes (accessors/mutators) to add to the model.
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Get additional fillable fields.
     */
    public function fillable(): array
    {
        return [];
    }

    /**
     * Get additional hidden fields.
     */
    public function hidden(): array
    {
        return [];
    }

    /**
     * Get additional casts.
     */
    public function casts(): array
    {
        return [];
    }

    /**
     * Get additional validation rules.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Apply relationships to the model.
     */
    protected function applyRelationships($model): void
    {
        foreach ($this->relationships() as $name => $closure) {
            $model->resolveRelationUsing($name, $closure);
        }
    }

    /**
     * Apply scopes to the model.
     */
    protected function applyScopes($model): void
    {
        foreach ($this->scopes() as $name => $closure) {
            $model->macro('scope' . ucfirst($name), $closure);
        }
    }

    /**
     * Apply attributes to the model.
     */
    protected function applyAttributes($model): void
    {
        foreach ($this->attributes() as $name => $closure) {
            $model->macro('get' . ucfirst($name) . 'Attribute', $closure);
        }
    }

    /**
     * Apply fillable fields to the model.
     */
    protected function applyFillable($model): void
    {
        $fillable = $this->fillable();
        if (!empty($fillable)) {
            $existing = $model->getFillable();
            $model->fillable(array_unique(array_merge($existing, $fillable)));
        }
    }

    /**
     * Apply hidden fields to the model.
     */
    protected function applyHidden($model): void
    {
        $hidden = $this->hidden();
        if (!empty($hidden)) {
            $existing = $model->getHidden();
            $model->setHidden(array_unique(array_merge($existing, $hidden)));
        }
    }

    /**
     * Apply casts to the model.
     */
    protected function applyCasts($model): void
    {
        $casts = $this->casts();
        if (!empty($casts)) {
            $existing = $model->getCasts();
            $model->mergeCasts(array_merge($existing, $casts));
        }
    }

    /**
     * Check if this extension should be applied to the given model.
     */
    public function shouldApply($model): bool
    {
        return is_a($model, $this->target, true);
    }

    /**
     * Get the priority for this extension (lower = applied first).
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Get the module code this extension belongs to.
     */
    public function getModuleCode(): ?string
    {
        return null;
    }

    /**
     * Check if the extension's module is active.
     */
    public function isModuleActive(): bool
    {
        $moduleCode = $this->getModuleCode();

        if (!$moduleCode) {
            return true; // No module restriction
        }

        $registry = app(\XLinic\Framework\Core\Module\ModuleRegistry::class);
        return $registry->isActive($moduleCode);
    }
}