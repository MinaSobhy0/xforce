<?php

namespace App\Filament\Imports\Columns;

use Filament\Actions\Imports\ImportColumn;

class TranslatableImportColumn extends ImportColumn
{
    protected string $language = 'en';

    protected array $supportedLanguages = ['en', 'ar'];

    public function language(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function supportedLanguages(array $languages): static
    {
        $this->supportedLanguages = $languages;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Transform the value for a translatable field.
     * Merges the value into the existing JSON structure.
     */
    public function fillRecordFromState(mixed $record, mixed $state): void
    {
        $name = $this->getName();
        $language = $this->getLanguage();

        // Get existing translations or empty array
        $translations = $record->{$name} ?? [];
        if (!is_array($translations)) {
            $translations = [];
        }

        // Merge in the new value
        if ($state !== null && $state !== '') {
            $translations[$language] = $state;
        }

        $record->{$name} = $translations;
    }

    /**
     * Create a pair of columns for EN and AR translations.
     */
    public static function makeTranslatable(string $name): array
    {
        return [
            static::make("{$name}_en")
                ->label(fn() => __("core::import.fields.{$name}") . ' (EN)')
                ->language('en')
                ->fillRecordUsing(function ($record, $state) use ($name) {
                    $translations = $record->{$name} ?? [];
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                    if ($state !== null && $state !== '') {
                        $translations['en'] = $state;
                    }
                    $record->{$name} = $translations;
                }),
            static::make("{$name}_ar")
                ->label(fn() => __("core::import.fields.{$name}") . ' (AR)')
                ->language('ar')
                ->fillRecordUsing(function ($record, $state) use ($name) {
                    $translations = $record->{$name} ?? [];
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                    if ($state !== null && $state !== '') {
                        $translations['ar'] = $state;
                    }
                    $record->{$name} = $translations;
                }),
        ];
    }
}
