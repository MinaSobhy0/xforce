<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Columns\ConstantImportColumn;
use Filament\Actions\Imports\ImportColumn;
use Modules\Prescriptions\Models\MedicineCatalog;

class MedicineCatalogImporter extends BaseImporter
{
    protected static ?string $model = MedicineCatalog::class;

    protected static string|array $resolveRecordUsing = 'brand_name';

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('brand_name')
                ->label(__('core::import.fields.brand_name'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('generic_name')
                ->label(__('core::import.fields.generic_name'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('manufacturer')
                ->label(__('core::import.fields.manufacturer'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('category')
                ->label(__('core::import.fields.category'))
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return 'other';
                    }

                    $column = (new ConstantImportColumn('category'))
                        ->constants(MedicineCatalog::CATEGORIES)
                        ->defaultValue('other');

                    return $column->resolveConstant($state);
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('drug_class')
                ->label(__('core::import.fields.drug_class'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('form')
                ->label(__('core::import.fields.form'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('strength')
                ->label(__('core::import.fields.strength'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('strength_unit')
                ->label(__('core::import.fields.strength_unit'))
                ->rules(['nullable', 'string', 'max:20']),

            ImportColumn::make('default_dosage')
                ->label(__('core::import.fields.default_dosage'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('default_dosage_unit')
                ->label(__('core::import.fields.default_dosage_unit'))
                ->rules(['nullable', 'string', 'max:20']),

            ImportColumn::make('default_frequency')
                ->label(__('core::import.fields.default_frequency'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('default_duration')
                ->label(__('core::import.fields.default_duration'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : null)
                ->rules(['nullable', 'integer', 'min:1']),

            ImportColumn::make('default_duration_unit')
                ->label(__('core::import.fields.default_duration_unit'))
                ->rules(['nullable', 'string', 'max:20']),

            ImportColumn::make('default_route')
                ->label(__('core::import.fields.default_route'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('default_instructions')
                ->label(__('core::import.fields.default_instructions'))
                ->rules(['nullable', 'string']),

            ImportColumn::make('description')
                ->label(__('core::import.fields.description'))
                ->rules(['nullable', 'string']),

            ImportColumn::make('indications')
                ->label(__('core::import.fields.indications'))
                ->rules(['nullable', 'string']),

            ImportColumn::make('contraindications')
                ->label(__('core::import.fields.contraindications'))
                ->rules(['nullable', 'string']),

            ImportColumn::make('side_effects')
                ->label(__('core::import.fields.side_effects'))
                ->rules(['nullable', 'string']),

            ImportColumn::make('warnings')
                ->label(__('core::import.fields.warnings'))
                ->rules(['nullable', 'string']),

            ImportColumn::make('is_controlled')
                ->label(__('core::import.fields.is_controlled'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, false))
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('requires_prescription')
                ->label(__('core::import.fields.requires_prescription'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, true))
                ->rules(['nullable', 'boolean']),

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
