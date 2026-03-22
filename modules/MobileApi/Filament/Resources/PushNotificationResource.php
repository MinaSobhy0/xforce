<?php

namespace Modules\MobileApi\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\Models\User;
use Modules\MobileApi\Filament\Resources\PushNotificationResource\Pages;
use Modules\MobileApi\Models\PushNotification;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Mobile App';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('Push Notifications');
    }

    public static function getModelLabel(): string
    {
        return __('Push Notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Push Notifications');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Send Notification'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label(__('User'))
                            ->options(User::query()->pluck('email', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label(__('Type'))
                            ->options(PushNotification::TYPES)
                            ->required(),

                        Forms\Components\TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('body')
                            ->label(__('Message'))
                            ->required()
                            ->rows(3),

                        Forms\Components\KeyValue::make('data')
                            ->label(__('Additional Data'))
                            ->keyLabel(__('Key'))
                            ->valueLabel(__('Value')),
                    ])
                    ->columns(1),
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

                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'attendance_violation' => 'danger',
                        'time_off_approved' => 'success',
                        'time_off_rejected' => 'danger',
                        'payslip_ready' => 'info',
                        'appointment_reminder' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => PushNotification::TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('Title'))
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent', 'delivered' => 'success',
                        'read' => 'info',
                        'failed' => 'danger',
                        default => 'warning',
                    }),

                Tables\Columns\IconColumn::make('read_at')
                    ->label(__('Read'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => $record->read_at !== null),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'pending' => __('Pending'),
                        'sent' => __('Sent'),
                        'delivered' => __('Delivered'),
                        'read' => __('Read'),
                        'failed' => __('Failed'),
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(PushNotification::TYPES),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('User'))
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('unread')
                    ->label(__('Unread Only'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('read_at')),

                Tables\Filters\Filter::make('failed')
                    ->label(__('Failed Only'))
                    ->query(fn (Builder $query): Builder => $query->where('status', 'failed')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListPushNotifications::route('/'),
            'create' => Pages\CreatePushNotification::route('/create'),
            'view' => Pages\ViewPushNotification::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'failed')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
