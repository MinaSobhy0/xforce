<?php

namespace Modules\MobileApi\Filament\Resources\PushNotificationResource\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Modules\MobileApi\Filament\Resources\PushNotificationResource;
use Modules\MobileApi\Models\PushNotification;

class ViewPushNotification extends ViewRecord
{
    protected static string $resource = PushNotificationResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('Notification Details'))
                    ->schema([
                        TextEntry::make('user.full_name')
                            ->label(__('User')),

                        TextEntry::make('user.email')
                            ->label(__('Email')),

                        TextEntry::make('type')
                            ->label(__('Type'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => PushNotification::TYPES[$state] ?? $state),

                        TextEntry::make('title')
                            ->label(__('Title')),

                        TextEntry::make('body')
                            ->label(__('Message'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('Delivery Status'))
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'sent', 'delivered' => 'success',
                                'read' => 'info',
                                'failed' => 'danger',
                                default => 'warning',
                            }),

                        TextEntry::make('fcm_message_id')
                            ->label(__('FCM Message ID'))
                            ->copyable()
                            ->placeholder(__('N/A')),

                        TextEntry::make('sent_at')
                            ->label(__('Sent At'))
                            ->dateTime(),

                        TextEntry::make('delivered_at')
                            ->label(__('Delivered At'))
                            ->dateTime()
                            ->placeholder(__('Not delivered')),

                        TextEntry::make('read_at')
                            ->label(__('Read At'))
                            ->dateTime()
                            ->placeholder(__('Not read')),

                        TextEntry::make('error_message')
                            ->label(__('Error'))
                            ->columnSpanFull()
                            ->placeholder(__('No errors'))
                            ->color('danger'),
                    ])
                    ->columns(2),

                Section::make(__('Additional Data'))
                    ->schema([
                        TextEntry::make('data')
                            ->label(__('Payload'))
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : '-')
                            ->columnSpanFull()
                            ->prose(),

                        TextEntry::make('reference_type')
                            ->label(__('Reference Type'))
                            ->placeholder(__('N/A')),

                        TextEntry::make('reference_id')
                            ->label(__('Reference ID'))
                            ->placeholder(__('N/A')),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make(__('Timestamps'))
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('Created'))
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label(__('Updated'))
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
