<?php

namespace Modules\Treatments\Filament\Resources\ConsentTemplateResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Modules\Treatments\Filament\Resources\ConsentTemplateResource;

class ViewConsentTemplate extends ViewRecord
{
    protected static string $resource = ConsentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(fn () => $this->record->duplicate())
                ->requiresConfirmation(),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Template Information')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('translated_name')
                                    ->label('Name'),

                                Components\TextEntry::make('version')
                                    ->label('Version')
                                    ->badge(),

                                Components\IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),
                            ]),

                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('valid_days')
                                    ->label('Valid Days')
                                    ->suffix(' days')
                                    ->placeholder('No expiration'),

                                Components\IconEntry::make('requires_witness')
                                    ->label('Requires Witness')
                                    ->boolean(),

                                Components\IconEntry::make('requires_patient_signature')
                                    ->label('Requires Patient Signature')
                                    ->boolean(),
                            ]),
                    ]),

                Components\Section::make('Content Preview')
                    ->schema([
                        Components\TextEntry::make('translated_content')
                            ->label('Content')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Components\Section::make('Statistics')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('treatments_count')
                                    ->label('Used in Treatments')
                                    ->state(fn ($record) => $record->treatments()->count()),

                                Components\TextEntry::make('signed_forms_count')
                                    ->label('Signed Forms')
                                    ->state(fn ($record) => $record->signedForms()->count()),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
}
