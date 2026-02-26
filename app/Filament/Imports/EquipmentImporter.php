<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Columns\ConstantImportColumn;
use App\Filament\Imports\Columns\MonetaryImportColumn;
use Filament\Actions\Imports\ImportColumn;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;
use Modules\Equipment\Models\Equipment;

class EquipmentImporter extends BaseImporter
{
    protected static ?string $model = Equipment::class;

    protected static string|array $resolveRecordUsing = 'serial_number';

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label(__('core::import.fields.name'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('category')
                ->label(__('core::import.fields.category'))
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return 'other';
                    }

                    $column = (new ConstantImportColumn('category'))
                        ->constants(Equipment::CATEGORIES)
                        ->defaultValue('other');

                    return $column->resolveConstant($state);
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('manufacturer')
                ->label(__('core::import.fields.manufacturer'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('model')
                ->label(__('core::import.fields.model'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('serial_number')
                ->label(__('core::import.fields.serial_number'))
                ->rules(['nullable', 'string', 'max:100']),

            ImportColumn::make('branch_name')
                ->label(__('core::import.fields.branch'))
                ->requiredMapping()
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $tenantId = tenant()?->id ?? session('tenant_id');
                    $branch = Branch::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($state) {
                            $q->where('name', 'ILIKE', "%{$state}%")
                              ->orWhere('code', 'ILIKE', "%{$state}%");
                        })
                        ->first();

                    if ($branch) {
                        $record->branch_id = $branch->id;
                    }
                })
                ->rules(['required', 'string']),

            ImportColumn::make('room_name')
                ->label(__('core::import.fields.room'))
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $tenantId = tenant()?->id ?? session('tenant_id');
                    $room = Room::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($state) {
                            $q->where('name', 'ILIKE', "%{$state}%")
                              ->orWhere('code', 'ILIKE', "%{$state}%");
                        })
                        ->first();

                    if ($room) {
                        $record->room_id = $room->id;
                    }
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('purchase_date')
                ->label(__('core::import.fields.purchase_date'))
                ->castStateUsing(function ($state) {
                    return self::parseDate($state);
                })
                ->rules(['nullable', 'date']),

            ImportColumn::make('purchase_price')
                ->label(__('core::import.fields.purchase_price'))
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }
                    $record->purchase_price_minor = MonetaryImportColumn::makeMoney('purchase_price')
                        ->castStateUsing(fn($s) => $s)
                        ->castState($state, []);
                })
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('warranty_expiry')
                ->label(__('core::import.fields.warranty_expiry'))
                ->castStateUsing(function ($state) {
                    return self::parseDate($state);
                })
                ->rules(['nullable', 'date']),

            ImportColumn::make('depreciation_years')
                ->label(__('core::import.fields.depreciation_years'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : null)
                ->rules(['nullable', 'integer', 'min:1']),

            ImportColumn::make('max_shots')
                ->label(__('core::import.fields.max_shots'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : null)
                ->rules(['nullable', 'integer', 'min:0']),

            ImportColumn::make('status')
                ->label(__('core::import.fields.status'))
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return Equipment::STATUS_ACTIVE;
                    }

                    $column = (new ConstantImportColumn('status'))
                        ->constants(Equipment::STATUSES)
                        ->defaultValue(Equipment::STATUS_ACTIVE);

                    return $column->resolveConstant($state);
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('tracking_enabled')
                ->label(__('core::import.fields.tracking_enabled'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, false))
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('notes')
                ->label(__('core::import.fields.notes'))
                ->rules(['nullable', 'string']),
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

    protected static function parseDate(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $state);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($state);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }
}
