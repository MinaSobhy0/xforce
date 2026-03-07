<?php

namespace Modules\Patients\Filament\Resources\PatientResource\Pages;

use Modules\Patients\Filament\Resources\PatientResource;
use Modules\Patients\Filament\Pages\MedicalProfilePage;
use Modules\Packages\Models\PackageSubscription;
use Modules\Booking\Filament\Pages\CreateBooking;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Support\HtmlString;

class ViewPatient extends BaseViewRecord
{
    protected static string $resource = PatientResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('medical_profile')
                ->label(__('patients::patients.actions.medical_profile'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('info')
                ->url(fn ($record) => MedicalProfilePage::getUrl(['patient_id' => $record->id])),
            Actions\Action::make('book_appointment')
                ->label(__('patients::patients.actions.book_appointment'))
                ->icon('heroicon-o-calendar')
                ->color('success'),
            Actions\Action::make('send_message')
                ->label(__('patients::patients.actions.send_message'))
                ->icon('heroicon-o-chat-bubble-left')
                ->color('gray'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Patient Information')
                    ->columns(4)
                    ->schema([
                        Components\TextEntry::make('code')
                            ->label(__('patients::patients.fields.code'))
                            ->copyable(),

                        Components\TextEntry::make('full_name')
                            ->label(__('patients::patients.fields.full_name')),

                        Components\TextEntry::make('international_phone')
                            ->label(__('patients::patients.fields.phone'))
                            ->copyable()
                            ->default(fn ($record) => $record->phone),

                        Components\TextEntry::make('email')
                            ->label(__('patients::patients.fields.email'))
                            ->copyable(),

                        Components\TextEntry::make('age')
                            ->label(__('patients::patients.fields.age'))
                            ->suffix(' years'),

                        Components\TextEntry::make('gender')
                            ->label(__('patients::patients.fields.gender'))
                            ->badge(),

                        Components\TextEntry::make('status')
                            ->label(__('patients::patients.fields.status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'gray',
                                'blocked' => 'danger',
                                default => 'gray',
                            }),

                        Components\TextEntry::make('referral_source')
                            ->label(__('patients::patients.fields.referral_source')),
                    ]),

                // Balance Alert Section
                Components\Section::make(__('patients::patients.balance.outstanding_balance'))
                    ->icon('heroicon-o-banknotes')
                    ->iconColor(fn ($record) => $record->balance_status_color)
                    ->visible(fn ($record) => $record->balance_minor != 0)
                    ->columns(3)
                    ->schema([
                        Components\TextEntry::make('balance_minor')
                            ->label(__('patients::patients.balance.title'))
                            ->formatStateUsing(fn ($state) => number_format(abs($state) / 100, 2) . ' ' . current_currency())
                            ->color(fn ($record) => $record->balance_status_color)
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->suffix(fn ($record) => $record->balance_minor > 0 ? ' ' . __('patients::patients.balance.owes') : ' ' . __('patients::patients.balance.credit')),

                        Components\TextEntry::make('balance_status_label')
                            ->label(__('patients::patients.fields.status'))
                            ->badge()
                            ->color(fn ($record) => $record->balance_status_color),

                        Components\ViewEntry::make('balance_actions')
                            ->hiddenLabel()
                            ->view('patients::components.balance-actions')
                            ->viewData(fn ($record) => ['patient' => $record]),
                    ]),

                Components\Section::make('Statistics')
                    ->columns(4)
                    ->schema([
                        Components\TextEntry::make('total_visits')
                            ->label('Total Visits')
                            ->numeric(),

                        Components\TextEntry::make('last_visit_at')
                            ->label(__('patients::patients.fields.last_visit'))
                            ->dateTime('d/m/Y H:i'),

                        Components\TextEntry::make('total_spent_minor')
                            ->label('Total Spent')
                            ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP'),

                        Components\TextEntry::make('loyalty_points')
                            ->label('Loyalty Points')
                            ->numeric(),
                    ]),

                // Active Packages Section
                Components\Section::make(__('packages::packages.sections.active_packages'))
                    ->icon('heroicon-o-gift')
                    ->iconColor('success')
                    ->visible(fn ($record) => $record->packageSubscriptions()->active()->exists())
                    ->schema([
                        Components\ViewEntry::make('active_packages')
                            ->hiddenLabel()
                            ->view('packages::components.patient-packages-widget')
                            ->viewData(fn ($record) => [
                                'subscriptions' => $record->packageSubscriptions()
                                    ->active()
                                    ->with(['package.items.service', 'appointments' => fn ($q) => $q->where('is_package_session', true)->whereIn('status', [
                                        \Modules\Booking\Models\Appointment::STATUS_SCHEDULED,
                                        \Modules\Booking\Models\Appointment::STATUS_CONFIRMED,
                                        \Modules\Booking\Models\Appointment::STATUS_CHECKED_IN,
                                        \Modules\Booking\Models\Appointment::STATUS_IN_PROGRESS,
                                    ])])
                                    ->orderBy('expires_at')
                                    ->get(),
                                'patientId' => $record->id,
                            ]),
                    ]),

                Components\Section::make('Medical Information')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        Components\TextEntry::make('medicalHistory.fitzpatrick_type')
                            ->label(__('patients::patients.medical.fitzpatrick_type'))
                            ->badge(),

                        Components\TextEntry::make('medicalHistory.blood_type')
                            ->label(__('patients::patients.medical.blood_type')),

                        Components\TextEntry::make('medicalHistory.bmi')
                            ->label('BMI')
                            ->suffix(fn ($record) => $record->medicalHistory?->bmi_category ? " ({$record->medicalHistory->bmi_category})" : ''),

                        Components\TextEntry::make('medicalHistory.allergies')
                            ->label(__('patients::patients.medical.allergies'))
                            ->badge()
                            ->separator(',')
                            ->columnSpanFull(),

                        Components\TextEntry::make('medicalHistory.contraindications')
                            ->label(__('patients::patients.medical.contraindications'))
                            ->badge()
                            ->color('danger')
                            ->separator(',')
                            ->columnSpanFull(),
                    ]),

                // AMR Summary Section
                Components\Section::make(__('patients::amr.amr_summary'))
                    ->visible(fn ($record) => $record->amrSummary?->has_any_data)
                    ->collapsible()
                    ->icon('heroicon-o-beaker')
                    ->iconColor(fn ($record) => $record->amrSummary?->has_critical_resistance ? 'danger' : 'warning')
                    ->columns(2)
                    ->schema([
                        Components\TextEntry::make('amrSummary.last_test_date')
                            ->label(__('patients::amr.last_test_date'))
                            ->date('M d, Y'),

                        Components\TextEntry::make('amrSummary.mdro_flags_display')
                            ->label(__('patients::amr.mdro_flags'))
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record) => !empty($record->amrSummary?->mdro_flags)),

                        Components\TextEntry::make('amrSummary.known_organisms')
                            ->label(__('patients::amr.known_organisms'))
                            ->badge()
                            ->color('gray')
                            ->separator(', ')
                            ->columnSpanFull(),

                        Components\TextEntry::make('amrSummary.known_resistances')
                            ->label(__('patients::amr.known_resistances'))
                            ->badge()
                            ->color('danger')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_map(fn ($a) => \Modules\Patients\Models\PatientAmrTest::getAntibioticLabel($a), $state)) : $state)
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->amrSummary?->known_resistances)),

                        Components\TextEntry::make('amrSummary.known_sensitivities')
                            ->label(__('patients::amr.known_sensitivities'))
                            ->badge()
                            ->color('success')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_map(fn ($a) => \Modules\Patients\Models\PatientAmrTest::getAntibioticLabel($a), $state)) : $state)
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->amrSummary?->known_sensitivities)),

                        Components\TextEntry::make('amrSummary.alert_notes')
                            ->label(__('patients::amr.alert_notes'))
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->amrSummary?->alert_notes),
                    ]),

                Components\Section::make('Alert Notes')
                    ->visible(fn ($record) => $record->notes()->activeAlerts()->exists())
                    ->schema([
                        Components\RepeatableEntry::make('notes')
                            ->hiddenLabel()
                            ->schema([
                                Components\TextEntry::make('content')
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->contained(false)
                            ->getStateUsing(fn ($record) => $record->notes()->activeAlerts()->get()),
                    ]),

                // Visit History Section
                Components\Section::make(__('booking::visits.navigation'))
                    ->icon('heroicon-o-ticket')
                    ->iconColor('purple')
                    ->collapsible()
                    ->collapsed(fn ($record) => $record->visits()->count() > 5)
                    ->schema([
                        Components\ViewEntry::make('visit_history')
                            ->hiddenLabel()
                            ->view('booking::components.patient-visit-history')
                            ->viewData(fn ($record) => [
                                'visits' => $record->visits()
                                    ->with([
                                        'appointments.service',
                                        'appointments.practitioner',
                                        'products.product',
                                        'invoice',
                                    ])
                                    ->limit(10)
                                    ->get(),
                                'patientId' => $record->id,
                            ]),
                    ]),
            ]);
    }
}
