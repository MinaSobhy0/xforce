<?php

namespace Modules\Packages\Filament\Resources\PackageResource\RelationManagers;

use Modules\Packages\Models\PackageSubscription;
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

    protected static ?string $title = 'Subscriptions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('patient_id')
                    ->label(__('packages::packages.fields.patient'))
                    ->options(fn () => Patient::all()->pluck('full_name', 'id'))
                    ->required()
                    ->searchable(),

                Forms\Components\DateTimePicker::make('purchased_at')
                    ->label(__('packages::packages.fields.purchased_at'))
                    ->required()
                    ->default(now()),

                Forms\Components\DateTimePicker::make('expires_at')
                    ->label(__('packages::packages.fields.expires_at'))
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label(__('packages::packages.fields.notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('packages::packages.fields.patient'))
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('packages::packages.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => PackageSubscription::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => PackageSubscription::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('sessions_used')
                    ->label(__('packages::packages.fields.sessions_used'))
                    ->getStateUsing(fn (PackageSubscription $record) =>
                        $record->sessions_used . ' / ' . ($record->package?->total_sessions ?? 0)
                    ),

                Tables\Columns\TextColumn::make('usage_progress')
                    ->label(__('packages::packages.fields.progress'))
                    ->getStateUsing(fn (PackageSubscription $record) => $record->usage_progress . '%'),

                Tables\Columns\TextColumn::make('purchased_at')
                    ->label(__('packages::packages.fields.purchased_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('packages::packages.fields.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn (PackageSubscription $record) => $record->isExpiringSoon() ? 'danger' : null),

                Tables\Columns\TextColumn::make('days_until_expiry')
                    ->label(__('packages::packages.fields.days_remaining'))
                    ->getStateUsing(fn (PackageSubscription $record) =>
                        $record->days_until_expiry !== null ? $record->days_until_expiry . ' days' : '-'
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('packages::packages.fields.status'))
                    ->options(PackageSubscription::STATUSES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Calculate expiry based on package validity
                        if (empty($data['expires_at'])) {
                            $package = $this->getOwnerRecord();
                            $data['expires_at'] = now()->addDays($package->validity_days);
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('freeze')
                    ->label(__('packages::packages.actions.freeze'))
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (PackageSubscription $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (PackageSubscription $record) {
                        if ($record->freeze()) {
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
                    ->visible(fn (PackageSubscription $record) => $record->isActive() || $record->isFrozen())
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
}
