<?php

namespace Modules\Memberships\Filament\Resources\MembershipResource\RelationManagers;

use Modules\Memberships\Models\MembershipSubscription;
use Modules\Patients\Models\Patient;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class SubscriptionsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Members';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('patient_id')
                    ->label(__('memberships::memberships.fields.patient'))
                    ->options(fn () => Patient::all()->pluck('full_name', 'id'))
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('billing_cycle')
                    ->label(__('memberships::memberships.fields.billing_cycle'))
                    ->options(MembershipSubscription::BILLING_CYCLES)
                    ->required()
                    ->default(MembershipSubscription::BILLING_MONTHLY),

                Forms\Components\DateTimePicker::make('started_at')
                    ->label(__('memberships::memberships.fields.started_at'))
                    ->required()
                    ->default(now()),

                Forms\Components\DateTimePicker::make('expires_at')
                    ->label(__('memberships::memberships.fields.expires_at'))
                    ->required(),

                Forms\Components\Toggle::make('auto_renew')
                    ->label(__('memberships::memberships.fields.auto_renew'))
                    ->default(true),

                Forms\Components\Textarea::make('notes')
                    ->label(__('memberships::memberships.fields.notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('memberships::memberships.fields.patient'))
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('memberships::memberships.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => MembershipSubscription::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => MembershipSubscription::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label(__('memberships::memberships.fields.billing_cycle'))
                    ->formatStateUsing(fn ($state) => MembershipSubscription::BILLING_CYCLES[$state] ?? $state),

                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('memberships::memberships.fields.started_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('memberships::memberships.fields.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn (MembershipSubscription $record) => $record->isExpiringSoon() ? 'danger' : null),

                Tables\Columns\TextColumn::make('days_until_expiry')
                    ->label(__('memberships::memberships.fields.days_remaining'))
                    ->getStateUsing(fn (MembershipSubscription $record) =>
                        $record->days_until_expiry !== null ? $record->days_until_expiry . ' days' : '-'
                    ),

                Tables\Columns\IconColumn::make('auto_renew')
                    ->label(__('memberships::memberships.fields.auto_renew'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('memberships::memberships.fields.status'))
                    ->options(MembershipSubscription::STATUSES),

                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->label(__('memberships::memberships.fields.billing_cycle'))
                    ->options(MembershipSubscription::BILLING_CYCLES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('freeze')
                    ->label(__('memberships::memberships.actions.freeze'))
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (MembershipSubscription $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (MembershipSubscription $record) {
                        if ($record->freeze()) {
                            Notification::make()
                                ->title(__('memberships::memberships.messages.frozen'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('unfreeze')
                    ->label(__('memberships::memberships.actions.unfreeze'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (MembershipSubscription $record) => $record->isFrozen())
                    ->requiresConfirmation()
                    ->action(function (MembershipSubscription $record) {
                        if ($record->unfreeze()) {
                            Notification::make()
                                ->title(__('memberships::memberships.messages.unfrozen'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('renew')
                    ->label(__('memberships::memberships.actions.renew'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (MembershipSubscription $record) => $record->isExpired() || $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (MembershipSubscription $record) {
                        if ($record->renew()) {
                            Notification::make()
                                ->title(__('memberships::memberships.messages.renewed'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label(__('memberships::memberships.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (MembershipSubscription $record) => $record->isActive() || $record->isFrozen())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label(__('memberships::memberships.fields.cancellation_reason'))
                            ->required(),
                    ])
                    ->action(function (MembershipSubscription $record, array $data) {
                        if ($record->cancel($data['cancellation_reason'])) {
                            Notification::make()
                                ->title(__('memberships::memberships.messages.cancelled'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('started_at', 'desc');
    }
}
