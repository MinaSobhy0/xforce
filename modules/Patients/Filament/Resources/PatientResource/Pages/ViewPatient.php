<?php

namespace Modules\Patients\Filament\Resources\PatientResource\Pages;

use Modules\Patients\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('book_appointment')
                ->label(__('patients::patients.actions.book_appointment'))
                ->icon('heroicon-o-calendar')
                ->color('success'),
            Actions\Action::make('send_message')
                ->label(__('patients::patients.actions.send_message'))
                ->icon('heroicon-o-chat-bubble-left')
                ->color('info'),
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

                        Components\TextEntry::make('phone')
                            ->label(__('patients::patients.fields.phone'))
                            ->copyable(),

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
            ]);
    }
}
