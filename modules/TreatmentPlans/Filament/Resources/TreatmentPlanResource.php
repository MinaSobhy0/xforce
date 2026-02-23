<?php

namespace Modules\TreatmentPlans\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Modules\Patients\Models\Patient;
use Modules\Packages\Models\Package;
use Modules\Services\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class TreatmentPlanResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = TreatmentPlan::class;

    protected static ?string $moduleCode = 'treatment_plans';

    protected static ?string $permissionKey = 'treatment_plans';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Clinical';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('treatment_plans::treatment_plans.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('treatment_plans::treatment_plans.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('treatment_plans::treatment_plans.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('treatment_plans::treatment_plans.tabs.overview'))
                            ->schema([
                                Forms\Components\Section::make(__('treatment_plans::treatment_plans.sections.basic_info'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('patient_id')
                                                    ->label(__('treatment_plans::treatment_plans.fields.patient'))
                                                    ->relationship('patient', 'first_name')
                                                    ->getOptionLabelFromRecordUsing(fn (Patient $record) => $record->full_name)
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->columnSpan(1),

                                                Forms\Components\Select::make('branch_id')
                                                    ->label(__('treatment_plans::treatment_plans.fields.branch'))
                                                    ->relationship('branch', 'name')
                                                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->translated_name)
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->default(fn () => current_branch_id())
                                                    ->columnSpan(1),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('name.en')
                                                    ->label(__('treatment_plans::treatment_plans.fields.name_en'))
                                                    ->required()
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('name.ar')
                                                    ->label(__('treatment_plans::treatment_plans.fields.name_ar'))
                                                    ->maxLength(255),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Textarea::make('description.en')
                                                    ->label(__('treatment_plans::treatment_plans.fields.description_en'))
                                                    ->rows(2),

                                                Forms\Components\Textarea::make('description.ar')
                                                    ->label(__('treatment_plans::treatment_plans.fields.description_ar'))
                                                    ->rows(2),
                                            ]),

                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\Select::make('source')
                                                    ->label(__('treatment_plans::treatment_plans.fields.source'))
                                                    ->options(TreatmentPlan::SOURCES)
                                                    ->default(TreatmentPlan::SOURCE_MANUAL)
                                                    ->required(),

                                                Forms\Components\DatePicker::make('start_date')
                                                    ->label(__('treatment_plans::treatment_plans.fields.start_date')),

                                                Forms\Components\DatePicker::make('target_end_date')
                                                    ->label(__('treatment_plans::treatment_plans.fields.target_end_date')),
                                            ]),
                                    ]),

                                Forms\Components\Section::make(__('treatment_plans::treatment_plans.sections.package_info'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('recommended_package_id')
                                                    ->label(__('treatment_plans::treatment_plans.fields.recommended_package'))
                                                    ->options(fn () => Package::active()
                                                        ->get()
                                                        ->mapWithKeys(fn ($package) => [
                                                            $package->id => $package->translated_name . ' (' . $package->formatted_price . ')'
                                                        ]))
                                                    ->searchable()
                                                    ->helperText(__('treatment_plans::treatment_plans.package.no_recommendation')),

                                                Forms\Components\Placeholder::make('package_subscription_status')
                                                    ->label(__('treatment_plans::treatment_plans.fields.package_subscription'))
                                                    ->content(function ($record) {
                                                        if (!$record || !$record->package_subscription_id) {
                                                            return __('treatment_plans::treatment_plans.package.not_purchased');
                                                        }
                                                        $subscription = $record->packageSubscription;
                                                        return $subscription
                                                            ? __('treatment_plans::treatment_plans.package.sessions_remaining', ['count' => $subscription->sessions_remaining])
                                                            : __('treatment_plans::treatment_plans.package.not_purchased');
                                                    })
                                                    ->visibleOn('edit'),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Forms\Components\Section::make(__('treatment_plans::treatment_plans.sections.notes'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Textarea::make('notes')
                                                    ->label(__('treatment_plans::treatment_plans.fields.notes'))
                                                    ->rows(3),

                                                Forms\Components\Textarea::make('internal_notes')
                                                    ->label(__('treatment_plans::treatment_plans.fields.internal_notes'))
                                                    ->rows(3),
                                            ]),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('treatment_plans::treatment_plans.tabs.services'))
                            ->schema([
                                Forms\Components\Section::make(__('treatment_plans::treatment_plans.items.title'))
                                    ->schema([
                                        Forms\Components\Repeater::make('items')
                                            ->relationship('items')
                                            ->schema([
                                                Forms\Components\Grid::make(4)
                                                    ->schema([
                                                        Forms\Components\Select::make('service_id')
                                                            ->label(__('treatment_plans::treatment_plans.fields.service'))
                                                            ->options(fn () => Service::active()
                                                                ->get()
                                                                ->mapWithKeys(fn ($service) => [
                                                                    $service->id => $service->translated_name
                                                                ]))
                                                            ->required()
                                                            ->searchable()
                                                            ->columnSpan(2),

                                                        Forms\Components\TextInput::make('recommended_sessions')
                                                            ->label(__('treatment_plans::treatment_plans.fields.recommended_sessions'))
                                                            ->required()
                                                            ->numeric()
                                                            ->default(1)
                                                            ->minValue(1)
                                                            ->columnSpan(1),

                                                        Forms\Components\TextInput::make('session_interval_days')
                                                            ->label(__('treatment_plans::treatment_plans.fields.session_interval_days'))
                                                            ->numeric()
                                                            ->default(config('treatment_plans.default_session_interval_days', 7))
                                                            ->columnSpan(1),
                                                    ]),

                                                Forms\Components\Grid::make(3)
                                                    ->schema([
                                                        Forms\Components\Select::make('preferred_practitioner_id')
                                                            ->label(__('treatment_plans::treatment_plans.fields.preferred_practitioner'))
                                                            ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'therapist', 'practitioner']))
                                                                ->get()
                                                                ->pluck('name', 'id'))
                                                            ->searchable(),

                                                        Forms\Components\Select::make('preferred_day_of_week')
                                                            ->label(__('treatment_plans::treatment_plans.fields.preferred_day_of_week'))
                                                            ->multiple()
                                                            ->options([
                                                                0 => __('treatment_plans::treatment_plans.days_of_week.0'),
                                                                1 => __('treatment_plans::treatment_plans.days_of_week.1'),
                                                                2 => __('treatment_plans::treatment_plans.days_of_week.2'),
                                                                3 => __('treatment_plans::treatment_plans.days_of_week.3'),
                                                                4 => __('treatment_plans::treatment_plans.days_of_week.4'),
                                                                5 => __('treatment_plans::treatment_plans.days_of_week.5'),
                                                                6 => __('treatment_plans::treatment_plans.days_of_week.6'),
                                                            ]),

                                                        Forms\Components\Select::make('preferred_time_slot')
                                                            ->label(__('treatment_plans::treatment_plans.fields.preferred_time_slot'))
                                                            ->options(TreatmentPlanItem::TIME_SLOTS),
                                                    ]),

                                                Forms\Components\Textarea::make('notes')
                                                    ->label(__('treatment_plans::treatment_plans.fields.notes'))
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->defaultItems(0)
                                            ->addActionLabel(__('treatment_plans::treatment_plans.items.add'))
                                            ->reorderable()
                                            ->reorderableWithDragAndDrop()
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string =>
                                                isset($state['service_id'])
                                                    ? Service::find($state['service_id'])?->translated_name . ' - ' . ($state['recommended_sessions'] ?? 1) . ' sessions'
                                                    : null
                                            ),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('treatment_plans::treatment_plans.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('treatment_plans::treatment_plans.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('name.en')
                    ->label(__('treatment_plans::treatment_plans.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => TreatmentPlan::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => TreatmentPlan::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label(__('treatment_plans::treatment_plans.progress.overall'))
                    ->getStateUsing(fn (TreatmentPlan $record) => $record->progress_percentage)
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_sessions')
                    ->label(__('treatment_plans::treatment_plans.fields.completed_sessions'))
                    ->getStateUsing(fn (TreatmentPlan $record) =>
                        $record->total_completed_sessions . '/' . $record->total_recommended_sessions
                    ),

                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('treatment_plans::treatment_plans.sections.services'))
                    ->counts('items'),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('treatment_plans::treatment_plans.fields.branch'))
                    ->formatStateUsing(fn ($record) => $record->branch?->translated_name)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('treatment_plans::treatment_plans.fields.start_date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('target_end_date')
                    ->label(__('treatment_plans::treatment_plans.fields.target_end_date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('treatment_plans::treatment_plans.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('treatment_plans::treatment_plans.filters.status'))
                    ->options(TreatmentPlan::STATUSES),

                Tables\Filters\SelectFilter::make('patient_id')
                    ->label(__('treatment_plans::treatment_plans.filters.patient'))
                    ->relationship('patient', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (Patient $record) => $record->full_name)
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('treatment_plans::treatment_plans.filters.branch'))
                    ->relationship('branch', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->translated_name)
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('has_package')
                    ->label(__('treatment_plans::treatment_plans.filters.has_package'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('package_subscription_id'),
                        false: fn (Builder $query) => $query->whereNull('package_subscription_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
                Infolists\Components\Section::make(__('treatment_plans::treatment_plans.sections.basic_info'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->label(__('treatment_plans::treatment_plans.fields.code')),

                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('treatment_plans::treatment_plans.fields.status'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => TreatmentPlan::STATUSES[$state] ?? $state)
                                    ->color(fn ($state) => TreatmentPlan::STATUS_COLORS[$state] ?? 'gray'),

                                Infolists\Components\TextEntry::make('source')
                                    ->label(__('treatment_plans::treatment_plans.fields.source'))
                                    ->formatStateUsing(fn ($state) => TreatmentPlan::SOURCES[$state] ?? $state),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('patient.full_name')
                                    ->label(__('treatment_plans::treatment_plans.fields.patient')),

                                Infolists\Components\TextEntry::make('branch.name')
                                    ->label(__('treatment_plans::treatment_plans.fields.branch'))
                                    ->formatStateUsing(fn ($record) => $record->branch?->translated_name),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('translated_name')
                                    ->label(__('treatment_plans::treatment_plans.fields.name')),

                                Infolists\Components\TextEntry::make('description')
                                    ->label(__('treatment_plans::treatment_plans.fields.description'))
                                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('treatment_plans::treatment_plans.sections.progress'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('progress_percentage')
                                    ->label(__('treatment_plans::treatment_plans.progress.overall'))
                                    ->formatStateUsing(fn ($state) => $state . '%')
                                    ->badge()
                                    ->color(fn ($state) => match (true) {
                                        $state >= 100 => 'success',
                                        $state >= 50 => 'warning',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('sessions_display')
                                    ->label(__('treatment_plans::treatment_plans.fields.completed_sessions'))
                                    ->getStateUsing(fn (TreatmentPlan $record) =>
                                        $record->total_completed_sessions . ' / ' . $record->total_recommended_sessions
                                    ),

                                Infolists\Components\TextEntry::make('items_count')
                                    ->label(__('treatment_plans::treatment_plans.sections.services'))
                                    ->getStateUsing(fn (TreatmentPlan $record) => $record->items->count()),

                                Infolists\Components\TextEntry::make('days_remaining')
                                    ->label(__('treatment_plans::treatment_plans.progress.days_remaining'))
                                    ->formatStateUsing(fn ($state) => $state !== null ? $state . ' days' : '-'),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('treatment_plans::treatment_plans.sections.financials'))
                    ->schema([
                        Infolists\Components\Grid::make(5)
                            ->schema([
                                Infolists\Components\TextEntry::make('formatted_total_value')
                                    ->label(__('treatment_plans::treatment_plans.financials.total_value'))
                                    ->weight(FontWeight::Bold)
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('formatted_deposits')
                                    ->label(__('treatment_plans::treatment_plans.financials.deposits'))
                                    ->weight(FontWeight::Bold)
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('formatted_invoiced')
                                    ->label(__('treatment_plans::treatment_plans.financials.invoiced'))
                                    ->weight(FontWeight::Bold)
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('formatted_paid')
                                    ->label(__('treatment_plans::treatment_plans.financials.paid'))
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('formatted_balance')
                                    ->label(__('treatment_plans::treatment_plans.financials.balance'))
                                    ->weight(FontWeight::Bold)
                                    ->color(fn (TreatmentPlan $record) => $record->balance_minor > 0 ? 'danger' : 'success'),
                            ]),
                    ])
                    ->visible(fn (TreatmentPlan $record) => $record->isActive() || $record->isCompleted()),

                Infolists\Components\Section::make(__('treatment_plans::treatment_plans.sections.dates'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('start_date')
                                    ->label(__('treatment_plans::treatment_plans.fields.start_date'))
                                    ->date(),

                                Infolists\Components\TextEntry::make('target_end_date')
                                    ->label(__('treatment_plans::treatment_plans.fields.target_end_date'))
                                    ->date(),

                                Infolists\Components\TextEntry::make('actual_end_date')
                                    ->label(__('treatment_plans::treatment_plans.fields.actual_end_date'))
                                    ->date()
                                    ->visible(fn ($record) => $record->actual_end_date !== null),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label(__('treatment_plans::treatment_plans.fields.created_at'))
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make(__('treatment_plans::treatment_plans.sections.package_info'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('recommendedPackage.translated_name')
                                    ->label(__('treatment_plans::treatment_plans.fields.recommended_package'))
                                    ->default(__('treatment_plans::treatment_plans.package.no_recommendation')),

                                Infolists\Components\TextEntry::make('package_subscription_status')
                                    ->label(__('treatment_plans::treatment_plans.fields.package_subscription'))
                                    ->getStateUsing(function (TreatmentPlan $record) {
                                        if (!$record->package_subscription_id) {
                                            return __('treatment_plans::treatment_plans.package.not_purchased');
                                        }
                                        $subscription = $record->packageSubscription;
                                        return $subscription
                                            ? __('treatment_plans::treatment_plans.package.purchased') . ' - ' .
                                              __('treatment_plans::treatment_plans.package.sessions_remaining', ['count' => $subscription->sessions_remaining])
                                            : __('treatment_plans::treatment_plans.package.not_purchased');
                                    })
                                    ->badge()
                                    ->color(fn (TreatmentPlan $record) => $record->package_subscription_id ? 'success' : 'gray'),
                            ]),
                    ])
                    ->collapsible()
                    ->visible(fn (TreatmentPlan $record) => $record->recommended_package_id !== null),

                Infolists\Components\Section::make(__('treatment_plans::treatment_plans.sections.notes'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('notes')
                                    ->label(__('treatment_plans::treatment_plans.fields.notes'))
                                    ->default('-'),

                                Infolists\Components\TextEntry::make('internal_notes')
                                    ->label(__('treatment_plans::treatment_plans.fields.internal_notes'))
                                    ->default('-'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers\ItemsRelationManager::class,
            \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers\AppointmentsRelationManager::class,
            \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers\PaymentsRelationManager::class,
            \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\RelationManagers\InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages\ListTreatmentPlans::route('/'),
            'create' => \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages\CreateTreatmentPlan::route('/create'),
            'view' => \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages\ViewTreatmentPlan::route('/{record}'),
            'edit' => \Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages\EditTreatmentPlan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient', 'branch', 'items', 'recommendedPackage', 'packageSubscription']);
    }
}
