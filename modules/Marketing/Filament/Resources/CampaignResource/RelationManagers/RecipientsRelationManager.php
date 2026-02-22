<?php

namespace Modules\Marketing\Filament\Resources\CampaignResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Marketing\Models\CampaignRecipient;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Recipients';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('marketing::marketing.fields.patient'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('marketing::marketing.fields.phone'))
                    ->visible(fn () => $this->ownerRecord->channel !== 'email'),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('marketing::marketing.fields.email'))
                    ->visible(fn () => $this->ownerRecord->channel === 'email'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('marketing::marketing.fields.status'))
                    ->colors([
                        'gray' => 'pending',
                        'info' => 'sent',
                        'success' => fn ($state) => in_array($state, ['delivered', 'read']),
                        'danger' => fn ($state) => in_array($state, ['failed', 'bounced']),
                        'warning' => 'unsubscribed',
                    ])
                    ->formatStateUsing(fn (string $state) => CampaignRecipient::statuses()[$state] ?? $state),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('marketing::marketing.fields.sent_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label(__('marketing::marketing.fields.delivered_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('read_at')
                    ->label(__('marketing::marketing.fields.read_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('marketing::marketing.fields.error'))
                    ->limit(50)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('marketing::marketing.fields.status'))
                    ->options(CampaignRecipient::statuses()),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label(__('marketing::marketing.actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (CampaignRecipient $record) {
                        $record->update([
                            'status' => CampaignRecipient::STATUS_PENDING,
                            'error_message' => null,
                            'failed_at' => null,
                        ]);
                    })
                    ->visible(fn (CampaignRecipient $record) => $record->status === CampaignRecipient::STATUS_FAILED),
            ])
            ->bulkActions([])
            ->defaultSort('sent_at', 'desc');
    }
}
