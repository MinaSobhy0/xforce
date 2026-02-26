<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Modules\Inventory\Models\Supplier;

class SupplierImporter extends BaseImporter
{
    protected static ?string $model = Supplier::class;

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

            ImportColumn::make('contact_person')
                ->label(__('core::import.fields.contact_person'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('email')
                ->label(__('core::import.fields.email'))
                ->rules(['nullable', 'email', 'max:255']),

            ImportColumn::make('phone')
                ->label(__('core::import.fields.phone'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('mobile')
                ->label(__('core::import.fields.mobile'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('address')
                ->label(__('core::import.fields.address'))
                ->rules(['nullable', 'string', 'max:500']),

            ImportColumn::make('city')
                ->label(__('core::import.fields.city'))
                ->rules(['nullable', 'string', 'max:100']),

            ImportColumn::make('country')
                ->label(__('core::import.fields.country'))
                ->rules(['nullable', 'string', 'max:100']),

            ImportColumn::make('tax_number')
                ->label(__('core::import.fields.tax_number'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('payment_terms_days')
                ->label(__('core::import.fields.payment_terms_days'))
                ->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : 30)
                ->rules(['nullable', 'integer', 'min:0']),

            ImportColumn::make('currency_code')
                ->label(__('core::import.fields.currency_code'))
                ->castStateUsing(fn($state) => $state ?: 'EGP')
                ->rules(['nullable', 'string', 'max:3']),

            ImportColumn::make('notes')
                ->label(__('core::import.fields.notes'))
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
