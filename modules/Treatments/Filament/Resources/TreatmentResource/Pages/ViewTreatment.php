<?php

namespace Modules\Treatments\Filament\Resources\TreatmentResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Modules\Treatments\Filament\Resources\TreatmentResource;

class ViewTreatment extends BaseViewRecord
{
    protected static string $resource = TreatmentResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Basic Information')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('code')
                                    ->label('Code'),

                                Components\TextEntry::make('translated_name')
                                    ->label('Name'),

                                Components\TextEntry::make('category.translated_name')
                                    ->label('Category'),
                            ]),

                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('duration_minutes')
                                    ->label('Duration')
                                    ->suffix(' min'),

                                Components\TextEntry::make('buffer_minutes')
                                    ->label('Buffer')
                                    ->suffix(' min'),

                                Components\TextEntry::make('formatted_price')
                                    ->label('Base Price'),

                                Components\TextEntry::make('total_duration')
                                    ->label('Total Time')
                                    ->suffix(' min'),
                            ]),

                        Components\Grid::make(3)
                            ->schema([
                                Components\IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),

                                Components\IconEntry::make('is_bookable_online')
                                    ->label('Online Booking')
                                    ->boolean(),

                                Components\IconEntry::make('requires_consent')
                                    ->label('Requires Consent')
                                    ->boolean(),
                            ]),
                    ]),

                Components\Section::make('Safety Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('fitzpatrick_min')
                                    ->label('Min Fitzpatrick Type')
                                    ->placeholder('No restriction'),

                                Components\TextEntry::make('fitzpatrick_max')
                                    ->label('Max Fitzpatrick Type')
                                    ->placeholder('No restriction'),
                            ]),

                        Components\TextEntry::make('contraindications')
                            ->label('Contraindications')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('None specified'),
                    ])
                    ->collapsed(),

                Components\Section::make('Session Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('recommended_sessions')
                                    ->label('Recommended Sessions')
                                    ->placeholder('Not specified'),

                                Components\TextEntry::make('session_interval_days')
                                    ->label('Session Interval')
                                    ->suffix(' days')
                                    ->placeholder('Not specified'),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
}
