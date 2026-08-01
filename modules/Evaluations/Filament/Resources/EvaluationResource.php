<?php

namespace Modules\Evaluations\Filament\Resources;

use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Evaluations\Filament\Resources\EvaluationResource\Pages;
use Modules\Evaluations\Models\Evaluation;

class EvaluationResource extends Resource
{
    protected static ?string $model = Evaluation::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Operations';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.clinical');
    }

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationLabel(): string
    {
        return __('evaluations::evaluations.evaluations');
    }

    public static function getModelLabel(): string
    {
        return __('evaluations::evaluations.evaluation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('evaluations::evaluations.evaluations');
    }

    public static function canCreate(): bool
    {
        return false; // Evaluations are created from visits only
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('evaluations::evaluations.fields.date'))
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('evaluations::evaluations.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit.code')
                    ->label(__('evaluations::evaluations.fields.visit'))
                    ->url(fn (Evaluation $record) => $record->visit_id
                        ? \Modules\Booking\Filament\Resources\VisitResource::getUrl('view', ['record' => $record->visit_id])
                        : null
                    )
                    ->color('primary'),

                Tables\Columns\TextColumn::make('overall_rating')
                    ->label(__('evaluations::evaluations.fields.overall_rating'))
                    ->badge()
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state) => match (true) {
                        $state >= 4 => 'success',
                        $state <= 2 => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('satisfaction_score')
                    ->label(__('evaluations::evaluations.fields.nps'))
                    ->badge()
                    ->formatStateUsing(fn (?int $state) => $state !== null ? $state.'/10' : '-')
                    ->color(fn (?int $state, Evaluation $record) => $record->nps_category_color ?? 'gray'),

                Tables\Columns\IconColumn::make('has_feedback')
                    ->label(__('evaluations::evaluations.fields.has_feedback'))
                    ->boolean()
                    ->trueIcon('heroicon-o-chat-bubble-left-ellipsis')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('evaluations::evaluations.fields.source'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('evaluations::evaluations.sources.'.$state))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('evaluatedBy.name')
                    ->label(__('evaluations::evaluations.fields.evaluated_by'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('evaluations::evaluations.fields.branch'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('overall_rating')
                    ->label(__('evaluations::evaluations.fields.overall_rating'))
                    ->options([
                        '5' => '★★★★★ (5)',
                        '4' => '★★★★☆ (4)',
                        '3' => '★★★☆☆ (3)',
                        '2' => '★★☆☆☆ (2)',
                        '1' => '★☆☆☆☆ (1)',
                    ]),

                Tables\Filters\TernaryFilter::make('has_feedback')
                    ->label(__('evaluations::evaluations.fields.has_feedback'))
                    ->queries(
                        true: fn ($query) => $query->withFeedback(),
                        false: fn ($query) => $query->whereNull('feedback_text')->whereNull('improvement_suggestions'),
                    ),

                Tables\Filters\SelectFilter::make('source')
                    ->label(__('evaluations::evaluations.fields.source'))
                    ->options(Evaluation::SOURCES),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('evaluations::evaluations.filters.from')),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label(__('evaluations::evaluations.filters.until')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make(__('evaluations::evaluations.sections.visit_info'))
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('visit.code')
                                    ->label(__('evaluations::evaluations.fields.visit'))
                                    ->url(fn (Evaluation $record) => $record->visit_id
                                        ? \Modules\Booking\Filament\Resources\VisitResource::getUrl('view', ['record' => $record->visit_id])
                                        : null
                                    )
                                    ->color('primary'),

                                Components\TextEntry::make('patient.full_name')
                                    ->label(__('evaluations::evaluations.fields.patient')),

                                Components\TextEntry::make('branch.name')
                                    ->label(__('evaluations::evaluations.fields.branch')),

                                Components\TextEntry::make('created_at')
                                    ->label(__('evaluations::evaluations.fields.date'))
                                    ->dateTime('M d, Y H:i'),

                                Components\TextEntry::make('evaluatedBy.name')
                                    ->label(__('evaluations::evaluations.fields.evaluated_by'))
                                    ->placeholder('-'),

                                Components\TextEntry::make('source')
                                    ->label(__('evaluations::evaluations.fields.source'))
                                    ->badge()
                                    ->formatStateUsing(fn (string $state) => __('evaluations::evaluations.sources.'.$state)),
                            ]),
                    ]),

                Components\Section::make(__('evaluations::evaluations.sections.ratings'))
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('overall_rating')
                                    ->label(__('evaluations::evaluations.fields.overall_rating'))
                                    ->badge()
                                    ->size('lg')
                                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).' ('.$state.'/5)')
                                    ->color(fn (int $state) => match (true) {
                                        $state >= 4 => 'success',
                                        $state <= 2 => 'danger',
                                        default => 'warning',
                                    }),

                                Components\TextEntry::make('service_quality_rating')
                                    ->label(__('evaluations::evaluations.fields.service_quality_rating'))
                                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state).' ('.$state.'/5)' : '-')
                                    ->color(fn (?int $state) => match (true) {
                                        $state === null => 'gray',
                                        $state >= 4 => 'success',
                                        $state <= 2 => 'danger',
                                        default => 'warning',
                                    }),

                                Components\TextEntry::make('staff_friendliness_rating')
                                    ->label(__('evaluations::evaluations.fields.staff_friendliness_rating'))
                                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state).' ('.$state.'/5)' : '-')
                                    ->color(fn (?int $state) => match (true) {
                                        $state === null => 'gray',
                                        $state >= 4 => 'success',
                                        $state <= 2 => 'danger',
                                        default => 'warning',
                                    }),

                                Components\TextEntry::make('cleanliness_rating')
                                    ->label(__('evaluations::evaluations.fields.cleanliness_rating'))
                                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state).' ('.$state.'/5)' : '-')
                                    ->color(fn (?int $state) => match (true) {
                                        $state === null => 'gray',
                                        $state >= 4 => 'success',
                                        $state <= 2 => 'danger',
                                        default => 'warning',
                                    }),

                                Components\TextEntry::make('wait_time_rating')
                                    ->label(__('evaluations::evaluations.fields.wait_time_rating'))
                                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state).' ('.$state.'/5)' : '-')
                                    ->color(fn (?int $state) => match (true) {
                                        $state === null => 'gray',
                                        $state >= 4 => 'success',
                                        $state <= 2 => 'danger',
                                        default => 'warning',
                                    }),

                                Components\TextEntry::make('value_for_money_rating')
                                    ->label(__('evaluations::evaluations.fields.value_for_money_rating'))
                                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state).' ('.$state.'/5)' : '-')
                                    ->color(fn (?int $state) => match (true) {
                                        $state === null => 'gray',
                                        $state >= 4 => 'success',
                                        $state <= 2 => 'danger',
                                        default => 'warning',
                                    }),
                            ]),
                    ]),

                Components\Section::make(__('evaluations::evaluations.sections.nps'))
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('satisfaction_score')
                                    ->label(__('evaluations::evaluations.fields.satisfaction_score'))
                                    ->badge()
                                    ->formatStateUsing(fn (?int $state) => $state !== null ? $state.'/10' : '-')
                                    ->color(fn (?int $state, Evaluation $record) => $record->nps_category_color ?? 'gray'),

                                Components\TextEntry::make('nps_category')
                                    ->label(__('evaluations::evaluations.fields.nps_category'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state, Evaluation $record) => $record->nps_category_label ?? '-')
                                    ->color(fn (?string $state, Evaluation $record) => $record->nps_category_color ?? 'gray'),

                                Components\IconEntry::make('would_recommend')
                                    ->label(__('evaluations::evaluations.fields.would_recommend'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-hand-thumb-up')
                                    ->falseIcon('heroicon-o-hand-thumb-down')
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ]),
                    ])
                    ->visible(fn (Evaluation $record) => $record->satisfaction_score !== null || $record->would_recommend !== null),

                Components\Section::make(__('evaluations::evaluations.sections.feedback'))
                    ->schema([
                        Components\TextEntry::make('feedback_text')
                            ->label(__('evaluations::evaluations.fields.feedback_text'))
                            ->columnSpanFull()
                            ->placeholder(__('evaluations::evaluations.no_feedback')),

                        Components\TextEntry::make('improvement_suggestions')
                            ->label(__('evaluations::evaluations.fields.improvement_suggestions'))
                            ->columnSpanFull()
                            ->placeholder(__('evaluations::evaluations.no_suggestions')),
                    ])
                    ->visible(fn (Evaluation $record) => $record->has_feedback),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvaluations::route('/'),
            'view' => Pages\ViewEvaluation::route('/{record}'),
        ];
    }
}
