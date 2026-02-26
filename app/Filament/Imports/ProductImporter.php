<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Columns\ConstantImportColumn;
use App\Filament\Imports\Columns\MonetaryImportColumn;
use App\Filament\Imports\Columns\RelationshipImportColumn;
use Filament\Actions\Imports\ImportColumn;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;

class ProductImporter extends BaseImporter
{
    protected static ?string $model = Product::class;

    protected static string|array $resolveRecordUsing = 'sku';

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->label(__('core::import.fields.sku'))
                ->requiredMapping()
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
                        ->relationship(ProductCategory::class)
                        ->searchColumns(['name', 'code'])
                        ->translatable(true)
                        ->tenantColumn('tenant_id');

                    $record->category_id = $column->resolveRelationship($state, $tenantId);
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('unit')
                ->label(__('core::import.fields.unit'))
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return 'pcs';
                    }

                    $column = (new ConstantImportColumn('unit'))
                        ->constants(Product::UNITS)
                        ->defaultValue('pcs');

                    return $column->resolveConstant($state);
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('cost_price_minor')
                ->label(__('core::import.fields.cost_price'))
                ->castStateUsing(function ($state) {
                    return MonetaryImportColumn::makeMoney('cost_price_minor')
                        ->castStateUsing(fn($s) => $s)
                        ->castState($state, []);
                })
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('sell_price_minor')
                ->label(__('core::import.fields.sell_price'))
                ->castStateUsing(function ($state) {
                    return MonetaryImportColumn::makeMoney('sell_price_minor')
                        ->castStateUsing(fn($s) => $s)
                        ->castState($state, []);
                })
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('reorder_point')
                ->label(__('core::import.fields.reorder_point'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : 10)
                ->rules(['nullable', 'integer', 'min:0']),

            ImportColumn::make('reorder_quantity')
                ->label(__('core::import.fields.reorder_quantity'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : 50)
                ->rules(['nullable', 'integer', 'min:0']),

            ImportColumn::make('barcode')
                ->label(__('core::import.fields.barcode'))
                ->rules(['nullable', 'string', 'max:100']),

            ImportColumn::make('is_consumable')
                ->label(__('core::import.fields.is_consumable'))
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
