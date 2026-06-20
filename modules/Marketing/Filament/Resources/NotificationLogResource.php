<?php

namespace Modules\Marketing\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Marketing\Filament\Resources\NotificationLogResource\Pages;
use Modules\Marketing\Models\NotificationLog;

class NotificationLogResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = NotificationLog::class;

    protected static ?string $moduleCode = 'marketing';

    protected static ?string $permissionKey = 'notification_logs';

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationLabel(): string
    {
        return __('marketing::marketing.navigation.notification_logs');
    }

    public static function getModelLabel(): string
    {
        return __('marketing::marketing.labels.notification_log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketing::marketing.labels.notification_logs');
    }

    public static function canCreate(): bool
    {
        return false; // Logs are created by the system
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('recipient_address')
                            ->label(__('marketing::marketing.fields.recipient'))
                            ->disabled(),

                        Forms\Components\TextInput::make('channel')
                            ->label(__('marketing::marketing.fields.channel'))
                            ->disabled(),

                        Forms\Components\TextInput::make('type')
                            ->label(__('marketing::marketing.fields.type'))
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label(__('marketing::marketing.fields.status'))
                            ->disabled(),

                        Forms\Components\Textarea::make('content')
                            ->label(__('marketing::marketing.fields.content'))
                            ->disabled()
                            ->rows(5),

                        Forms\Components\Textarea::make('error_message')
                            ->label(__('marketing::marketing.fields.error'))
                            ->disabled()
                            ->visible(fn ($record) => $record?->error_message),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('marketing::marketing.fields.patient'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recipient_address')
                    ->label(__('marketing::marketing.fields.recipient'))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->colors([
                        'success' => 'whatsapp',
                        'info' => 'sms',
                        'primary' => 'email',
                    ]),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('marketing::marketing.fields.type'))
                    ->formatStateUsing(fn (string $state) => NotificationLog::types()[$state] ?? $state),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('marketing::marketing.fields.status'))
                    ->colors([
                        'gray' => 'queued',
                        'info' => fn ($state) => in_array($state, ['sending', 'sent']),
                        'success' => fn ($state) => in_array($state, ['delivered', 'read']),
                        'danger' => fn ($state) => in_array($state, ['failed', 'bounced']),
                    ]),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('marketing::marketing.fields.sent_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label(__('marketing::marketing.fields.delivered_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('marketing::marketing.fields.error'))
                    ->limit(30)
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->options(NotificationLog::channels()),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('marketing::marketing.fields.type'))
                    ->options(NotificationLog::types()),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('marketing::marketing.fields.status'))
                    ->options([
                        'queued' => 'Queued',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                        'bounced' => 'Bounced',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label(__('marketing::marketing.filters.today'))
                    ->query(fn ($query) => $query->today()),

                Tables\Filters\Filter::make('failed')
                    ->label(__('marketing::marketing.filters.failed_only'))
                    ->query(fn ($query) => $query->failed()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('retry')
                    ->label(__('marketing::marketing.actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (NotificationLog $record) {
                        // Reset status and re-queue
                        $record->update([
                            'status' => NotificationLog::STATUS_QUEUED,
                            'error_message' => null,
                            'failed_at' => null,
                            'queued_at' => now(),
                        ]);
                        // TODO: Dispatch job to resend
                    })
                    ->visible(fn (NotificationLog $record) => $record->status === NotificationLog::STATUS_FAILED),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
            'view' => Pages\ViewNotificationLog::route('/{record}'),
        ];
    }
}
