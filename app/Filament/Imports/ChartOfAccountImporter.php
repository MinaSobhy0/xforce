<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Columns\ConstantImportColumn;
use App\Filament\Imports\Columns\RelationshipImportColumn;
use Filament\Actions\Imports\ImportColumn;
use Modules\Accounting\Models\ChartOfAccount;

class ChartOfAccountImporter extends BaseImporter
{
    protected static ?string $model = ChartOfAccount::class;

    protected static string|array $resolveRecordUsing = 'code';

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label(__('core::import.fields.code'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:20']),

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

            ImportColumn::make('type')
                ->label(__('core::import.fields.account_type'))
                ->requiredMapping()
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    $column = (new ConstantImportColumn('type'))
                        ->constants(ChartOfAccount::TYPES_FLAT);

                    return $column->resolveConstant($state);
                })
                ->rules(['required', 'string']),

            ImportColumn::make('parent_code')
                ->label(__('core::import.fields.parent_account'))
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $tenantId = tenant()?->id ?? session('tenant_id');
                    $parent = ChartOfAccount::where('tenant_id', $tenantId)
                        ->where('code', $state)
                        ->first();

                    if ($parent) {
                        $record->parent_id = $parent->id;
                    }
                })
                ->rules(['nullable', 'string']),

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
