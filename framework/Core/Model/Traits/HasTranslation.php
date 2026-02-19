<?php

namespace XLinic\Framework\Core\Model\Traits;

use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Builder;

trait HasTranslation
{
    use HasTranslations;

    /**
     * Get the translatable attributes.
     */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    /**
     * Get available locales for this model.
     */
    public function getAvailableLocales(): array
    {
        $locales = [];

        foreach ($this->getTranslatableAttributes() as $attribute) {
            $translations = $this->getTranslations($attribute);
            $locales = array_merge($locales, array_keys($translations));
        }

        return array_unique($locales);
    }

    /**
     * Check if translation exists for locale.
     */
    public function hasTranslation(string $attribute, ?string $locale = null): bool
    {
        $locale = $locale ?? app()->getLocale();

        return !empty($this->getTranslation($attribute, $locale, false));
    }

    /**
     * Get translation or fallback.
     */
    public function getTranslationWithFallback(string $attribute, ?string $locale = null): mixed
    {
        $locale = $locale ?? app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        // Try requested locale first
        $translation = $this->getTranslation($attribute, $locale, false);

        if (!empty($translation)) {
            return $translation;
        }

        // Try fallback locale
        if ($locale !== $fallbackLocale) {
            $translation = $this->getTranslation($attribute, $fallbackLocale, false);

            if (!empty($translation)) {
                return $translation;
            }
        }

        // Return any available translation
        $translations = $this->getTranslations($attribute);

        return !empty($translations) ? array_values($translations)[0] : null;
    }

    /**
     * Set translation for specific locale.
     */
    public function setTranslationForLocale(string $attribute, string $locale, mixed $value): self
    {
        $translations = $this->getTranslations($attribute);
        $translations[$locale] = $value;

        return $this->setTranslations($attribute, $translations);
    }

    /**
     * Remove translation for specific locale.
     */
    public function removeTranslation(string $attribute, string $locale): self
    {
        $translations = $this->getTranslations($attribute);
        unset($translations[$locale]);

        return $this->setTranslations($attribute, $translations);
    }

    /**
     * Get completion percentage for translations.
     */
    public function getTranslationCompleteness(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $translatableAttributes = $this->getTranslatableAttributes();
        $total = count($translatableAttributes);

        if ($total === 0) {
            return ['percentage' => 100, 'completed' => 0, 'total' => 0];
        }

        $completed = 0;

        foreach ($translatableAttributes as $attribute) {
            if ($this->hasTranslation($attribute, $locale)) {
                $completed++;
            }
        }

        return [
            'percentage' => round(($completed / $total) * 100, 2),
            'completed' => $completed,
            'total' => $total,
        ];
    }

    /**
     * Scope to models with translation in locale.
     */
    public function scopeWhereTranslation(Builder $query, string $attribute, string $value, ?string $locale = null): Builder
    {
        $locale = $locale ?? app()->getLocale();

        return $query->where("{$attribute}->{$locale}", $value);
    }

    /**
     * Scope to models with translation like value.
     */
    public function scopeWhereTranslationLike(Builder $query, string $attribute, string $value, ?string $locale = null): Builder
    {
        $locale = $locale ?? app()->getLocale();

        return $query->where("{$attribute}->{$locale}", 'like', $value);
    }

    /**
     * Scope to models that have translation for locale.
     */
    public function scopeWhereHasTranslation(Builder $query, string $attribute, ?string $locale = null): Builder
    {
        $locale = $locale ?? app()->getLocale();

        return $query->whereNotNull("{$attribute}->{$locale}");
    }

    /**
     * Scope to models missing translation for locale.
     */
    public function scopeWhereMissingTranslation(Builder $query, string $attribute, ?string $locale = null): Builder
    {
        $locale = $locale ?? app()->getLocale();

        return $query->whereNull("{$attribute}->{$locale}");
    }

    /**
     * Get all missing translations for this model.
     */
    public function getMissingTranslations(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $translatableAttributes = $this->getTranslatableAttributes();
        $missing = [];

        foreach ($translatableAttributes as $attribute) {
            if (!$this->hasTranslation($attribute, $locale)) {
                $missing[] = $attribute;
            }
        }

        return $missing;
    }

    /**
     * Bulk set translations for locale.
     */
    public function setTranslationsForLocale(array $translations, string $locale): self
    {
        foreach ($translations as $attribute => $value) {
            if (in_array($attribute, $this->getTranslatableAttributes())) {
                $this->setTranslationForLocale($attribute, $locale, $value);
            }
        }

        return $this;
    }

    /**
     * Get display name with translation support.
     */
    public function getDisplayNameTranslated(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        // Check if name is translatable
        if (in_array('name', $this->getTranslatableAttributes())) {
            return $this->getTranslationWithFallback('name', $locale) ?? $this->getDisplayName();
        }

        // Check if title is translatable
        if (in_array('title', $this->getTranslatableAttributes())) {
            return $this->getTranslationWithFallback('title', $locale) ?? $this->getDisplayName();
        }

        return $this->getDisplayName();
    }
}