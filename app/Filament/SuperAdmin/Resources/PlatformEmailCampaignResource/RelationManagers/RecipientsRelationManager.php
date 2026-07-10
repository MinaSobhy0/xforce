<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\RelationManagers;

use App\Models\PlatformEmailCampaignRecipient;
use App\Models\PlatformEmailSuppression;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $recordTitleAttribute = 'email';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_hint')->wrap()->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => PlatformEmailCampaignRecipient::STATUS_PENDING,
                        'info' => PlatformEmailCampaignRecipient::STATUS_RENDERING,
                        'warning' => PlatformEmailCampaignRecipient::STATUS_SENDING,
                        'success' => PlatformEmailCampaignRecipient::STATUS_SENT,
                        'success' => PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                        'danger' => PlatformEmailCampaignRecipient::STATUS_FAILED,
                        'danger' => PlatformEmailCampaignRecipient::STATUS_BOUNCED,
                    ])
                    ->formatStateUsing(fn ($state) => PlatformEmailCampaignRecipient::STATUSES[$state] ?? $state),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('opened_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('first_clicked_at')->label('Clicked at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('ai_cost_usd_cents')
                    ->label('AI $')
                    ->formatStateUsing(fn ($state) => $state ? '$'.number_format(($state ?? 0) / 100, 4) : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('error_message')->wrap()->limit(50)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PlatformEmailCampaignRecipient::STATUSES),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_bounced')
                    ->label('Mark bounced')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Adds this address to the global suppression list. Future campaigns skip it silently.')
                    ->visible(fn ($record) => in_array($record->status, [
                        PlatformEmailCampaignRecipient::STATUS_SENT,
                        PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                        PlatformEmailCampaignRecipient::STATUS_FAILED,
                    ], true))
                    ->action(function ($record): void {
                        PlatformEmailSuppression::add(
                            email: $record->email,
                            reason: PlatformEmailSuppression::REASON_BOUNCED,
                            extra: ['campaign_id' => $record->campaign_id, 'added_by_user_id' => auth()->id()],
                        );
                        $record->update(['status' => PlatformEmailCampaignRecipient::STATUS_BOUNCED]);
                        $record->campaign?->increment('bounced_count');
                        Notification::make()->title('Suppressed')->success()->send();
                    }),

                Tables\Actions\Action::make('mark_complained')
                    ->label('Mark spam complaint')
                    ->icon('heroicon-o-flag')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Same as bounce, but flagged as spam complaint (worse for reputation — do only when a real complaint has arrived).')
                    ->visible(fn ($record) => in_array($record->status, [
                        PlatformEmailCampaignRecipient::STATUS_SENT,
                        PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                    ], true))
                    ->action(function ($record): void {
                        PlatformEmailSuppression::add(
                            email: $record->email,
                            reason: PlatformEmailSuppression::REASON_COMPLAINED,
                            extra: ['campaign_id' => $record->campaign_id, 'added_by_user_id' => auth()->id()],
                        );
                        $record->update(['status' => PlatformEmailCampaignRecipient::STATUS_BOUNCED]);
                        $record->campaign?->increment('complained_count');
                        Notification::make()->title('Complaint recorded — address suppressed')->success()->send();
                    }),
            ])
            ->defaultSort('id')
            ->paginated([25, 50, 100]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
