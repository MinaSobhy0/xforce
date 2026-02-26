<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Columns\ConstantImportColumn;
use Filament\Actions\Imports\ImportColumn;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;

class JournalImporter extends BaseImporter
{
    protected static ?string $model = Journal::class;

    protected static string|array $resolveRecordUsing = 'code';

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label(__('core::import.fields.code'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:10']),

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
                ->label(__('core::import.fields.journal_type'))
                ->requiredMapping()
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return Journal::TYPE_GENERAL;
                    }

                    $column = (new ConstantImportColumn('type'))
                        ->constants(Journal::TYPES)
                        ->defaultValue(Journal::TYPE_GENERAL);

                    return $column->resolveConstant($state);
                })
                ->rules(['required', 'string']),

            ImportColumn::make('sequence_prefix')
                ->label(__('core::import.fields.sequence_prefix'))
                ->rules(['nullable', 'string', 'max:10']),

            ImportColumn::make('default_debit_account_code')
                ->label(__('core::import.fields.default_debit_account'))
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $tenantId = tenant()?->id ?? session('tenant_id');
                    $account = ChartOfAccount::where('tenant_id', $tenantId)
                        ->where('code', $state)
                        ->first();

                    if ($account) {
                        $record->default_debit_account_id = $account->id;
                    }
                })
                ->rules(['nullable', 'string']),

            ImportColumn::make('default_credit_account_code')
                ->label(__('core::import.fields.default_credit_account'))
                ->fillRecordUsing(function ($record, $state) {
                    if ($state === null || $state === '') {
                        return;
                    }

                    $tenantId = tenant()?->id ?? session('tenant_id');
                    $account = ChartOfAccount::where('tenant_id', $tenantId)
                        ->where('code', $state)
                        ->first();

                    if ($account) {
                        $record->default_credit_account_id = $account->id;
                    }
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
