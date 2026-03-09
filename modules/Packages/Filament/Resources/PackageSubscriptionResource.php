<?php

namespace Modules\Packages\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Filament\Resources\PackageSubscriptionResource\Pages;
use Modules\Packages\Filament\Resources\PackageSubscriptionResource\RelationManagers;

class PackageSubscriptionResource extends Resource
{
    protected static ?string $model = PackageSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Packages';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('packages::packages.subscriptions.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('packages::packages.subscriptions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('packages::packages.subscriptions.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('packages::packages.subscriptions.sections.details'))
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label(__('packages::packages.fields.patient'))
                            ->relationship('patient', 'full_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(),

                        Forms\Components\Select::make('package_id')
                            ->label(__('packages::packages.fields.package'))
                            ->relationship('package', 'name')
                            ->required()
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label(__('packages::packages.fields.status'))
                            ->options(PackageSubscription::STATUSES)
                            ->required()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('purchased_at')
                            ->label(__('packages::packages.fields.purchased_at'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('packages::packages.fields.expires_at')),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('packages::packages.fields.notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('packages::packages.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('package.translated_name')
                    ->label(__('packages::packages.fields.package'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('packages::packages.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => PackageSubscription::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => PackageSubscription::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('formatted_price')
                    ->label(__('packages::packages.fields.price'))
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('package_price_minor', $direction)),

                Tables\Columns\TextColumn::make('usage_display')
                    ->label(__('packages::packages.subscriptions.fields.usage'))
                    ->getStateUsing(function (PackageSubscription $record) {
                        $package = $record->package;
                        if ($package?->isPulseBased()) {
                            return number_format($record->pulses_used) . ' / ' . number_format($package->total_pulses);
                        }
                        return $record->sessions_used . ' / ' . ($package?->total_sessions ?? 0);
                    }),

                Tables\Columns\TextColumn::make('usage_progress')
                    ->label(__('packages::packages.fields.progress'))
                    ->getStateUsing(fn (PackageSubscription $record) => $record->usage_progress . '%')
                    ->badge()
                    ->color(fn (PackageSubscription $record) => match (true) {
                        $record->usage_progress >= 100 => 'success',
                        $record->usage_progress >= 75 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('formatted_balance')
                    ->label(__('packages::packages.subscriptions.fields.balance'))
                    ->color(fn (PackageSubscription $record) => $record->hasBalance() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('purchased_at')
                    ->label(__('packages::packages.fields.purchased_at'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('packages::packages.fields.expires_at'))
                    ->date()
                    ->sortable()
                    ->color(fn (PackageSubscription $record) => $record->isExpiringSoon() ? 'danger' : null),

                Tables\Columns\TextColumn::make('days_until_expiry')
                    ->label(__('packages::packages.fields.days_remaining'))
                    ->getStateUsing(fn (PackageSubscription $record) =>
                        $record->days_until_expiry !== null ? $record->days_until_expiry . ' ' . __('packages::packages.fields.days') : '-'
                    )
                    ->color(fn (PackageSubscription $record) =>
                        $record->days_until_expiry !== null && $record->days_until_expiry <= 7 ? 'danger' : null
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('packages::packages.fields.status'))
                    ->options(PackageSubscription::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('package_id')
                    ->label(__('packages::packages.fields.package'))
                    ->relationship('package', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label(__('packages::packages.subscriptions.filters.expiring_soon'))
                    ->query(fn ($query) => $query->expiringSoon(30)),

                Tables\Filters\Filter::make('has_balance')
                    ->label(__('packages::packages.subscriptions.filters.has_balance'))
                    ->query(fn ($query) => $query->where('balance_remaining_minor', '>', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('freeze')
                    ->label(__('packages::packages.actions.freeze'))
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (PackageSubscription $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('frozen_until')
                            ->label(__('packages::packages.subscriptions.fields.frozen_until'))
                            ->minDate(now()->addDay())
                            ->maxDate(now()->addDays(90)),
                    ])
                    ->action(function (PackageSubscription $record, array $data) {
                        $until = $data['frozen_until'] ? \Carbon\Carbon::parse($data['frozen_until']) : null;
                        if ($record->freeze($until)) {
                            Notification::make()
                                ->title(__('packages::packages.messages.frozen'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('unfreeze')
                    ->label(__('packages::packages.actions.unfreeze'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (PackageSubscription $record) => $record->isFrozen())
                    ->requiresConfirmation()
                    ->action(function (PackageSubscription $record) {
                        if ($record->unfreeze()) {
                            Notification::make()
                                ->title(__('packages::packages.messages.unfrozen'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('packages::packages.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (PackageSubscription $record) => $record->canTransitionTo(PackageSubscription::STATUS_CANCELLED))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label(__('packages::packages.fields.cancellation_reason'))
                            ->required(),
                    ])
                    ->action(function (PackageSubscription $record, array $data) {
                        if ($record->cancel($data['cancellation_reason'])) {
                            Notification::make()
                                ->title(__('packages::packages.messages.cancelled'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('purchased_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('packages::packages.subscriptions.sections.details'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('patient.full_name')
                                    ->label(__('packages::packages.fields.patient'))
                                    ->url(fn (PackageSubscription $record) => route('filament.admin.resources.patients.view', $record->patient_id)),

                                Infolists\Components\TextEntry::make('package.translated_name')
                                    ->label(__('packages::packages.fields.package')),

                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('packages::packages.fields.status'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => PackageSubscription::STATUSES[$state] ?? $state)
                                    ->color(fn ($state) => PackageSubscription::STATUS_COLORS[$state] ?? 'gray'),
                            ]),

                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('purchased_at')
                                    ->label(__('packages::packages.fields.purchased_at'))
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('expires_at')
                                    ->label(__('packages::packages.fields.expires_at'))
                                    ->dateTime()
                                    ->color(fn (PackageSubscription $record) => $record->isExpiringSoon() ? 'danger' : null),

                                Infolists\Components\TextEntry::make('days_until_expiry')
                                    ->label(__('packages::packages.fields.days_remaining'))
                                    ->getStateUsing(fn (PackageSubscription $record) =>
                                        $record->days_until_expiry !== null ? $record->days_until_expiry . ' ' . __('packages::packages.fields.days') : '-'
                                    ),

                                Infolists\Components\TextEntry::make('branch.name')
                                    ->label(__('packages::packages.subscriptions.fields.branch')),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('packages::packages.subscriptions.sections.usage'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('consumption_total')
                                    ->label(__('packages::packages.subscriptions.fields.total_sessions'))
                                    ->getStateUsing(fn (PackageSubscription $record) =>
                                        $record->consumption_total . ' ' . ($record->package?->isPulseBased() ? __('packages::packages.labels.pulses') : __('packages::packages.labels.sessions'))
                                    ),

                                Infolists\Components\TextEntry::make('consumption_used')
                                    ->label(__('packages::packages.subscriptions.fields.used'))
                                    ->color('danger'),

                                Infolists\Components\TextEntry::make('sessions_booked')
                                    ->label(__('packages::packages.subscriptions.fields.booked'))
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('consumption_remaining')
                                    ->label(__('packages::packages.subscriptions.fields.remaining'))
                                    ->color('success'),
                            ]),

                        Infolists\Components\TextEntry::make('usage_progress')
                            ->label(__('packages::packages.fields.progress'))
                            ->getStateUsing(fn (PackageSubscription $record) => $record->usage_progress . '%')
                            ->badge()
                            ->color(fn (PackageSubscription $record) => match (true) {
                                $record->usage_progress >= 100 => 'success',
                                $record->usage_progress >= 75 => 'warning',
                                default => 'gray',
                            }),
                    ]),

                Infolists\Components\Section::make(__('packages::packages.subscriptions.sections.payment'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('formatted_price')
                                    ->label(__('packages::packages.subscriptions.fields.package_price')),

                                Infolists\Components\TextEntry::make('total_paid')
                                    ->label(__('packages::packages.subscriptions.fields.paid'))
                                    ->formatStateUsing(fn ($state) => format_money($state))
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('formatted_balance')
                                    ->label(__('packages::packages.subscriptions.fields.balance'))
                                    ->color(fn (PackageSubscription $record) => $record->hasBalance() ? 'danger' : 'success'),

                                Infolists\Components\TextEntry::make('payment_progress')
                                    ->label(__('packages::packages.subscriptions.fields.payment_progress'))
                                    ->getStateUsing(fn (PackageSubscription $record) => $record->payment_progress . '%')
                                    ->badge()
                                    ->color(fn (PackageSubscription $record) => $record->isFullyPaid() ? 'success' : 'warning'),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('packages::packages.subscriptions.sections.services'))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('package.items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('service.translated_name')
                                    ->label(__('packages::packages.fields.service')),

                                Infolists\Components\TextEntry::make('quantity')
                                    ->label(__('packages::packages.fields.quantity'))
                                    ->suffix(fn ($record) => ' ' . ($record->consumption_type === 'pulses' ? __('packages::packages.labels.pulses') : __('packages::packages.labels.sessions'))),

                                Infolists\Components\TextEntry::make('formatted_unit_price')
                                    ->label(__('packages::packages.fields.unit_price')),
                            ])
                            ->columns(3),
                    ]),

                Infolists\Components\Section::make(__('packages::packages.fields.notes'))
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('')
                            ->markdown(),
                    ])
                    ->collapsed()
                    ->visible(fn (PackageSubscription $record) => filled($record->notes)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsagesRelationManager::class,
            RelationManagers\AppointmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackageSubscriptions::route('/'),
            'view' => Pages\ViewPackageSubscription::route('/{record}'),
        ];
    }
}
