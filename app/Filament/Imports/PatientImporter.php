<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Columns\ArrayImportColumn;
use App\Filament\Imports\Columns\ConstantImportColumn;
use Filament\Actions\Imports\ImportColumn;
use Modules\Patients\Models\Patient;

class PatientImporter extends BaseImporter
{
    protected static ?string $model = Patient::class;

    protected static string|array $resolveRecordUsing = 'email';

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label(__('core::import.fields.code'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('first_name')
                ->label(__('core::import.fields.first_name'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('last_name')
                ->label(__('core::import.fields.last_name'))
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('email')
                ->label(__('core::import.fields.email'))
                ->rules(['nullable', 'email', 'max:255']),

            ImportColumn::make('phone')
                ->label(__('core::import.fields.phone'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('secondary_phone')
                ->label(__('core::import.fields.secondary_phone'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('date_of_birth')
                ->label(__('core::import.fields.date_of_birth'))
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    // Try various date formats
                    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
                    foreach ($formats as $format) {
                        $date = \DateTime::createFromFormat($format, $state);
                        if ($date !== false) {
                            return $date->format('Y-m-d');
                        }
                    }

                    // Try strtotime as fallback
                    $timestamp = strtotime($state);
                    if ($timestamp !== false) {
                        return date('Y-m-d', $timestamp);
                    }

                    return null;
                })
                ->rules(['nullable', 'date']),

            ImportColumn::make('gender')
                ->label(__('core::import.fields.gender'))
                ->castStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    $state = strtolower(trim((string) $state));

                    $genderMap = [
                        'male' => 'male',
                        'm' => 'male',
                        'ذكر' => 'male',
                        'female' => 'female',
                        'f' => 'female',
                        'أنثى' => 'female',
                    ];

                    return $genderMap[$state] ?? $state;
                })
                ->rules(['nullable', 'string', 'in:male,female']),

            ImportColumn::make('national_id')
                ->label(__('core::import.fields.national_id'))
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

            ImportColumn::make('occupation')
                ->label(__('core::import.fields.occupation'))
                ->rules(['nullable', 'string', 'max:100']),

            ImportColumn::make('emergency_contact_name')
                ->label(__('core::import.fields.emergency_contact_name'))
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('emergency_contact_phone')
                ->label(__('core::import.fields.emergency_contact_phone'))
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('emergency_contact_relation')
                ->label(__('core::import.fields.emergency_contact_relation'))
                ->rules(['nullable', 'string', 'max:100']),

            ImportColumn::make('referral_source')
                ->label(__('core::import.fields.referral_source'))
                ->rules(['nullable', 'string', 'max:100']),

            ImportColumn::make('language')
                ->label(__('core::import.fields.language'))
                ->castStateUsing(fn($state) => $state ?: 'en')
                ->rules(['nullable', 'string', 'max:10']),

            ImportColumn::make('tags')
                ->label(__('core::import.fields.tags'))
                ->castStateUsing(function ($state) {
                    return ArrayImportColumn::makeArray('tags')
                        ->castStateUsing(fn($s) => $s)
                        ->castState($state, []);
                })
                ->rules(['nullable', 'array']),

            ImportColumn::make('notes')
                ->label(__('core::import.fields.notes'))
                ->rules(['nullable', 'string']),

            ImportColumn::make('marketing_consent')
                ->label(__('core::import.fields.marketing_consent'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, false))
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('sms_consent')
                ->label(__('core::import.fields.sms_consent'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, false))
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('email_consent')
                ->label(__('core::import.fields.email_consent'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, false))
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('whatsapp_consent')
                ->label(__('core::import.fields.whatsapp_consent'))
                ->castStateUsing(fn($state) => self::parseBoolean($state, false))
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
