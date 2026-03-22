<?php

namespace Modules\MobileApi\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\MobileApi\Filament\Resources\DeviceTokenResource\Pages;
use Modules\MobileApi\Models\DeviceToken;
use Modules\MobileApi\Services\PushNotificationService;

class DeviceTokenResource extends Resource
{
    protected static ?string $model = DeviceToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Mobile App';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'device_id';

    public static function getNavigationLabel(): string
    {
        return __('Registered Devices');
    }

    public static function getModelLabel(): string
    {
        return __('Device');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Devices');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Device Information'))
                    ->schema([
                        Forms\Components\TextInput::make('device_id')
                            ->label(__('Device ID'))
                            ->disabled(),

                        Forms\Components\TextInput::make('platform')
                            ->label(__('Platform'))
                            ->disabled(),

                        Forms\Components\TextInput::make('app_version')
                            ->label(__('App Version'))
                            ->disabled(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Active'))
                            ->helperText(__('Deactivate to stop sending notifications to this device')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('User'))
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('device_id')
                    ->label(__('Device ID'))
                    ->limit(20)
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('platform')
                    ->label(__('Platform'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ios' => 'gray',
                        'android' => 'success',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => DeviceToken::PLATFORMS[$state] ?? $state),

                Tables\Columns\TextColumn::make('app_version')
                    ->label(__('Version'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('Last Used'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Registered'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label(__('Platform'))
                    ->options(DeviceToken::PLATFORMS),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('send_test')
                    ->label(__('Test Push'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(__('Send Test Notification'))
                    ->modalDescription(__('Send a test push notification to this device?'))
                    ->action(function (DeviceToken $record) {
                        $service = app(PushNotificationService::class);

                        $notification = $service->sendToUser(
                            user: $record->user,
                            type: 'test',
                            title: __('Test Notification'),
                            body: __('This is a test notification from the admin panel.'),
                            checkPreferences: false,
                        );

                        if ($notification && $notification->status === 'sent') {
                            Notification::make()
                                ->title(__('Test notification sent'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('Failed to send notification'))
                                ->body($notification?->error_message ?? __('Unknown error'))
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (DeviceToken $record) => $record->is_active),

                Tables\Actions\Action::make('deactivate')
                    ->label(__('Deactivate'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (DeviceToken $record) => $record->deactivate())
                    ->visible(fn (DeviceToken $record) => $record->is_active),

                Tables\Actions\Action::make('activate')
                    ->label(__('Activate'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (DeviceToken $record) => $record->activate())
                    ->visible(fn (DeviceToken $record) => ! $record->is_active),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deactivate')
                    ->label(__('Deactivate Selected'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->deactivate()),

                Tables\Actions\BulkAction::make('activate')
                    ->label(__('Activate Selected'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn ($records) => $records->each->activate()),
            ])
            ->defaultSort('last_used_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceTokens::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Devices are registered via API only
    }
}
