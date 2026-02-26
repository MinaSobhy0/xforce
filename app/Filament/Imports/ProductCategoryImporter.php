<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Modules\Inventory\Models\ProductCategory;

class ProductCategoryImporter extends BaseImporter
{
    protected static ?string $model = ProductCategory::class;

    protected static string|array $resolveRecordUsing = [];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name_en')
                ->label(__('core::import.fields.name') . ' (EN)')
                ->requiredMapping()
                ->fillRecordUsing(function ($record, $state) {
                    $translations = $record->name ?? [];
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                    if ($state !== null && $state !== '') {
                        $translations['en'] = $state;
                    }
                    $record->name = $translations;
                })
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('name_ar')
                ->label(__('core::import.fields.name') . ' (AR)')
                ->fillRecordUsing(function ($record, $state) {
                    $translations = $record->name ?? [];
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                    if ($state !== null && $state !== '') {
                        $translations['ar'] = $state;
                    }
                    $record->name = $translations;
                })
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('description_en')
                ->label(__('core::import.fields.description') . ' (EN)')
                ->fillRecordUsing(function ($record, $state) {
                    $translations = $record->description ?? [];
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                    if ($state !== null && $state !== '') {
                        $translations['en'] = $state;
                    }
                    $record->description = $translations;
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('description_ar')
                ->label(__('core::import.fields.description') . ' (AR)')
                ->fillRecordUsing(function ($record, $state) {
                    $translations = $record->description ?? [];
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                    if ($state !== null && $state !== '') {
                        $translations['ar'] = $state;
                    }
                    $record->description = $translations;
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('parent_name')
                ->label(__('core::import.fields.parent_category'))
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $tenantId = tenant()?->id ?? session('tenant_id');
                    $parent = ProductCategory::where('tenant_id', $tenantId)
                        ->whereRaw("name->>'en' ILIKE ?", ["%{$state}%"])
                        ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$state}%"])
                        ->first();

                    if ($parent) {
                        $record->parent_id = $parent->id;
                    }
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('sort_order')
                ->label(__('core::import.fields.sort_order'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : 0)
                ->rules(['nullable', 'integer', 'min:0']),

            ImportColumn::make('is_active')
                ->label(__('core::import.fields.is_active'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, true))
                ->rules(['nullable', 'boolean']),
        ];
    }

    protected static function parseBoolean(mixed $state, bool $default = false): bool
    {
        if ($state === null || $state === '') {
            return $default;
        }

        if (is_bool($state)) {
            return $state;
        }

        $state = strtolower(trim((string) $state));

        return in_array($state, ['1', 'true', 'yes', 'on', 'y', 'active', 'enabled'], true);
    }
}
