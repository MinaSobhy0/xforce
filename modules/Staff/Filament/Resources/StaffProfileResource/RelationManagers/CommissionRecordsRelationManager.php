<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Staff\Models\StaffCommissionRecord;

class CommissionRecordsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'commissionRecords';

    protected static ?string $title = 'Commission History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('staff::staff.fields.date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('appointment.code')
                    ->label(__('staff::staff.fields.appointment'))
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('revenue')
                    ->label(__('staff::staff.fields.revenue'))
                    ->money(current_currency()),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('staff::staff.fields.type'))
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->badge(),

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label(__('staff::staff.fields.rate'))
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('staff::staff.fields.amount'))
                    ->money(current_currency())
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('staff::staff.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => StaffCommissionRecord::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => StaffCommissionRecord::STATUS_COLORS[$state] ?? 'gray'),

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
                    ->options(StaffCommissionRecord::STATUSES),
            ])
            ->headerActions([])
            ->actions([
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

                Tables\Actions\Action::make('cancel')
                    ->label(__('staff::staff.actions.cancel'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (StaffCommissionRecord $record) => $record->canTransitionTo(StaffCommissionRecord::STATUS_CANCELLED))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('staff::staff.fields.notes'))
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
                Tables\Actions\BulkAction::make('approve_selected')
                    ->label(__('staff::staff.actions.approve_selected'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->approve(auth()->id())) {
                                $count++;
                            }
                        }
                        Notification::make()
                            ->title(__('staff::staff.messages.approved_count', ['count' => $count]))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
