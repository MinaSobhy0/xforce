<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Columns\MonetaryImportColumn;
use App\Filament\Imports\Columns\RelationshipImportColumn;
use Filament\Actions\Imports\ImportColumn;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;

class ServiceImporter extends BaseImporter
{
    protected static ?string $model = Service::class;

    protected static string|array $resolveRecordUsing = 'code';

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label(__('core::import.fields.code'))
                ->rules(['nullable', 'string', 'max:50']),

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

            ImportColumn::make('category_id')
                ->label(__('core::import.fields.category'))
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $tenantId = tenant()?->id ?? session('tenant_id');
                    $column = (new RelationshipImportColumn('category_id'))
                        ->relationship(ServiceCategory::class)
                        ->searchColumns(['name', 'code'])
                        ->translatable(true)
                        ->tenantColumn('tenant_id');

                    $record->category_id = $column->resolveRelationship($state, $tenantId);
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('duration_minutes')
                ->label(__('core::import.fields.duration_minutes'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : 30)
                ->rules(['nullable', 'integer', 'min:1']),

            ImportColumn::make('buffer_minutes')
                ->label(__('core::import.fields.buffer_minutes'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : 0)
                ->rules(['nullable', 'integer', 'min:0']),

            ImportColumn::make('base_price_minor')
                ->label(__('core::import.fields.price'))
                ->castStateUsing(function ($state) {
                    return MonetaryImportColumn::makeMoney('base_price_minor')
                        ->castStateUsing(fn($s) => $s)
                        ->castState($state, []);
                })
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('recommended_sessions')
                ->label(__('core::import.fields.recommended_sessions'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : null)
                ->rules(['nullable', 'integer', 'min:1']),

            ImportColumn::make('session_interval_days')
                ->label(__('core::import.fields.session_interval_days'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : null)
                ->rules(['nullable', 'integer', 'min:1']),

            ImportColumn::make('is_active')
                ->label(__('core::import.fields.is_active'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, true))
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('requires_consent')
                ->label(__('core::import.fields.requires_consent'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, false))
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('is_bookable_online')
                ->label(__('core::import.fields.is_bookable_online'))
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
