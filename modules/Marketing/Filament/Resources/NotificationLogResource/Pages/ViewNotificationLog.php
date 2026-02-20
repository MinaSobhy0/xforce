<?php

namespace Modules\Marketing\Filament\Resources\NotificationLogResource\Pages;

use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Modules\Marketing\Filament\Resources\NotificationLogResource;
use Modules\Marketing\Models\NotificationLog;

class ViewNotificationLog extends ViewRecord
{
    protected static string $resource = NotificationLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('marketing::marketing.sections.notification_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('patient.full_name')
                            ->label(__('marketing::marketing.fields.patient')),

                        Infolists\Components\TextEntry::make('recipient_address')
                            ->label(__('marketing::marketing.fields.recipient')),

                        Infolists\Components\TextEntry::make('channel')
                            ->label(__('marketing::marketing.fields.channel'))
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'whatsapp' => 'success',
                                'sms' => 'info',
                                'email' => 'primary',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('type')
                            ->label(__('marketing::marketing.fields.type'))
                            ->formatStateUsing(fn (string $state) => NotificationLog::types()[$state] ?? $state),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('marketing::marketing.fields.status'))
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'queued' => 'gray',
                                'sending', 'sent' => 'info',
                                'delivered', 'read' => 'success',
                                'failed', 'bounced' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('marketing::marketing.sections.content'))
                    ->schema([
                        Infolists\Components\TextEntry::make('subject')
                            ->label(__('marketing::marketing.fields.subject'))
                            ->visible(fn () => $this->record->channel === 'email'),

                        Infolists\Components\TextEntry::make('content')
                            ->label(__('marketing::marketing.fields.content'))
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make(__('marketing::marketing.sections.timing'))
                    ->schema([
                        Infolists\Components\TextEntry::make('queued_at')
                            ->label(__('marketing::marketing.fields.queued_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('sent_at')
                            ->label(__('marketing::marketing.fields.sent_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('delivered_at')
                            ->label(__('marketing::marketing.fields.delivered_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('read_at')
                            ->label(__('marketing::marketing.fields.read_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('failed_at')
                            ->label(__('marketing::marketing.fields.failed_at'))
                            ->dateTime()
                            ->visible(fn () => $this->record->failed_at),
                    ])
                    ->columns(5),

                Infolists\Components\Section::make(__('marketing::marketing.sections.error'))
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label(__('marketing::marketing.fields.error'))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn () => $this->record->error_message)
                    ->collapsed(),

                Infolists\Components\Section::make(__('marketing::marketing.sections.provider_response'))
                    ->schema([
                        Infolists\Components\TextEntry::make('provider_message_id')
                            ->label(__('marketing::marketing.fields.provider_message_id')),

                        Infolists\Components\TextEntry::make('provider_response_json')
                            ->label(__('marketing::marketing.fields.provider_response'))
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn () => $this->record->provider_message_id || $this->record->provider_response_json)
                    ->collapsed(),
            ]);
    }
}
