<?php

namespace Modules\Marketing\Filament\Resources\CampaignResource\Pages;

use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Modules\Marketing\Filament\Resources\CampaignResource;
use Modules\Marketing\Models\Campaign;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),

            Actions\Action::make('schedule')
                ->label(__('marketing::marketing.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label(__('marketing::marketing.fields.scheduled_at'))
                        ->required()
                        ->native(false)
                        ->minDate(now()),
                ])
                ->action(function (array $data) {
                    $this->record->schedule(new \DateTime($data['scheduled_at']));
                })
                ->visible(fn () => $this->record->status === Campaign::STATUS_DRAFT),

            Actions\Action::make('start_now')
                ->label(__('marketing::marketing.actions.start_now'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->record->startSending())
                ->visible(fn () => $this->record->status === Campaign::STATUS_SCHEDULED),

            Actions\Action::make('pause')
                ->label(__('marketing::marketing.actions.pause'))
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->action(fn () => $this->record->pause())
                ->visible(fn () => $this->record->status === Campaign::STATUS_SENDING),

            Actions\Action::make('resume')
                ->label(__('marketing::marketing.actions.resume'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(fn () => $this->record->resume())
                ->visible(fn () => $this->record->status === Campaign::STATUS_PAUSED),

            Actions\Action::make('cancel')
                ->label(__('marketing::marketing.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->record->cancel())
                ->visible(fn () => in_array($this->record->status, [
                    Campaign::STATUS_SCHEDULED,
                    Campaign::STATUS_SENDING,
                    Campaign::STATUS_PAUSED,
                ])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('marketing::marketing.sections.campaign_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label(__('marketing::marketing.fields.name')),

                        Infolists\Components\TextEntry::make('description')
                            ->label(__('marketing::marketing.fields.description')),

                        Infolists\Components\TextEntry::make('channel')
                            ->label(__('marketing::marketing.fields.channel'))
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'whatsapp' => 'success',
                                'sms' => 'info',
                                'email' => 'primary',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('marketing::marketing.fields.status'))
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'draft' => 'gray',
                                'scheduled' => 'info',
                                'sending' => 'warning',
                                'paused' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state) => Campaign::statuses()[$state] ?? $state),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make(__('marketing::marketing.sections.statistics'))
                    ->schema([
                        Infolists\Components\TextEntry::make('total_recipients')
                            ->label(__('marketing::marketing.fields.total_recipients')),

                        Infolists\Components\TextEntry::make('sent_count')
                            ->label(__('marketing::marketing.fields.sent')),

                        Infolists\Components\TextEntry::make('delivered_count')
                            ->label(__('marketing::marketing.fields.delivered')),

                        Infolists\Components\TextEntry::make('read_count')
                            ->label(__('marketing::marketing.fields.read')),

                        Infolists\Components\TextEntry::make('failed_count')
                            ->label(__('marketing::marketing.fields.failed'))
                            ->color('danger'),

                        Infolists\Components\TextEntry::make('delivery_rate')
                            ->label(__('marketing::marketing.fields.delivery_rate'))
                            ->suffix('%'),

                        Infolists\Components\TextEntry::make('read_rate')
                            ->label(__('marketing::marketing.fields.read_rate'))
                            ->suffix('%'),

                        Infolists\Components\TextEntry::make('progress_percentage')
                            ->label(__('marketing::marketing.fields.progress'))
                            ->suffix('%'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make(__('marketing::marketing.sections.timing'))
                    ->schema([
                        Infolists\Components\TextEntry::make('scheduled_at')
                            ->label(__('marketing::marketing.fields.scheduled_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('started_at')
                            ->label(__('marketing::marketing.fields.started_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('completed_at')
                            ->label(__('marketing::marketing.fields.completed_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('createdBy.name')
                            ->label(__('marketing::marketing.fields.created_by')),
                    ])
                    ->columns(4),
            ]);
    }
}
