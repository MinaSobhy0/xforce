<?php

namespace Modules\Staff\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Staff\Filament\Resources\CommissionRecordResource\Pages;
use Modules\Staff\Models\StaffCommissionRecord;

class CommissionRecordResource extends Resource
{
    protected static ?string $model = StaffCommissionRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 35;

    public static function getNavigationLabel(): string
    {
        return __('staff::staff.navigation.commission_records');
    }

    public static function getModelLabel(): string
    {
        return __('staff::staff.labels.commission_record');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff::staff.labels.commission_records');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pending()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('staff::staff.fields.date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staffProfile.user.full_name')
                    ->label(__('staff::staff.fields.staff'))
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('staffProfile.user', function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                              ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        return $query->join('staff_profiles', 'staff_commission_records.staff_profile_id', '=', 'staff_profiles.id')
                            ->join('users', 'staff_profiles.user_id', '=', 'users.id')
                            ->orderBy('users.first_name', $direction);
                    }),

                Tables\Columns\TextColumn::make('appointment.code')
                    ->label(__('staff::staff.fields.appointment'))
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('appointment.service.name')
                    ->label(__('staff::staff.fields.service'))
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('revenue')
                    ->label(__('staff::staff.fields.revenue'))
                    ->money(current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('staff::staff.fields.type'))
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label(__('staff::staff.fields.rate'))
                    ->suffix('%')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('staff::staff.fields.amount'))
                    ->money(current_currency())
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('staff::staff.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => StaffCommissionRecord::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => StaffCommissionRecord::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('staff::staff.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('approved_at')
                    ->label(__('staff::staff.fields.approved_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('staff::staff.fields.paid_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('staff::staff.fields.status'))
                    ->options(StaffCommissionRecord::STATUSES)
                    ->default('pending'),

                Tables\Filters\SelectFilter::make('staff_profile_id')
                    ->label(__('staff::staff.fields.staff'))
                    ->relationship('staffProfile', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? "Staff #{$record->id}")
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('staff::staff.fields.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('staff::staff.fields.until')),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label(__('staff::staff.actions.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (StaffCommissionRecord $record) => $record->canTransitionTo(StaffCommissionRecord::STATUS_APPROVED))
                    ->requiresConfirmation()
                    ->action(function (StaffCommissionRecord $record) {
                        if ($record->approve(auth()->id())) {
                            Notification::make()
                                ->title(__('staff::staff.messages.approved'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label(__('staff::staff.actions.mark_paid'))
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (StaffCommissionRecord $record) => $record->canTransitionTo(StaffCommissionRecord::STATUS_PAID))
                    ->requiresConfirmation()
                    ->action(function (StaffCommissionRecord $record) {
                        if ($record->markAsPaid()) {
                            Notification::make()
                                ->title(__('staff::staff.messages.marked_paid'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('staff::staff.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (StaffCommissionRecord $record) => $record->canTransitionTo(StaffCommissionRecord::STATUS_CANCELLED))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('staff::staff.fields.cancellation_reason'))
                            ->required(),
                    ])
                    ->action(function (StaffCommissionRecord $record, array $data) {
                        if ($record->cancel($data['notes'])) {
                            Notification::make()
                                ->title(__('staff::staff.messages.cancelled'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label(__('staff::staff.actions.approve_selected'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->canTransitionTo(StaffCommissionRecord::STATUS_APPROVED) && $record->approve(auth()->id())) {
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title(__('staff::staff.messages.approved_count', ['count' => $count]))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_paid_selected')
                        ->label(__('staff::staff.actions.mark_paid_selected'))
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->canTransitionTo(StaffCommissionRecord::STATUS_PAID) && $record->markAsPaid()) {
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title(__('staff::staff.messages.paid_count', ['count' => $count]))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('cancel_selected')
                        ->label(__('staff::staff.actions.cancel_selected'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('notes')
                                ->label(__('staff::staff.fields.cancellation_reason'))
                                ->required(),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->canTransitionTo(StaffCommissionRecord::STATUS_CANCELLED) && $record->cancel($data['notes'])) {
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title(__('staff::staff.messages.cancelled_count', ['count' => $count]))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('staff::staff.sections.commission_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('staffProfile.user.name')
                            ->label(__('staff::staff.fields.staff')),
                        Infolists\Components\TextEntry::make('appointment.code')
                            ->label(__('staff::staff.fields.appointment'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('staff::staff.fields.date'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('staff::staff.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => StaffCommissionRecord::STATUSES[$state] ?? $state)
                            ->color(fn ($state) => StaffCommissionRecord::STATUS_COLORS[$state] ?? 'gray'),
                    ])->columns(4),

                Infolists\Components\Section::make(__('staff::staff.sections.amounts'))
                    ->schema([
                        Infolists\Components\TextEntry::make('revenue')
                            ->label(__('staff::staff.fields.revenue'))
                            ->money(current_currency()),
                        Infolists\Components\TextEntry::make('commission_type')
                            ->label(__('staff::staff.fields.type'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),
                        Infolists\Components\TextEntry::make('commission_rate')
                            ->label(__('staff::staff.fields.rate'))
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('amount')
                            ->label(__('staff::staff.fields.amount'))
                            ->money(current_currency())
                            ->weight('bold')
                            ->color('success'),
                    ])->columns(4),

                Infolists\Components\Section::make(__('staff::staff.sections.approval'))
                    ->schema([
                        Infolists\Components\TextEntry::make('approvedBy.name')
                            ->label(__('staff::staff.fields.approved_by'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->label(__('staff::staff.fields.approved_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->label(__('staff::staff.fields.paid_at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])->columns(3),

                Infolists\Components\Section::make(__('staff::staff.fields.notes'))
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('staff::staff.fields.notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissionRecords::route('/'),
            'view' => Pages\ViewCommissionRecord::route('/{record}'),
        ];
    }
}
