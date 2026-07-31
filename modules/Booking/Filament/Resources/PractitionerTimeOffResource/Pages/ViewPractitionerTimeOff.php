<?php

namespace Modules\Booking\Filament\Resources\PractitionerTimeOffResource\Pages;

use Modules\Booking\Filament\Resources\PractitionerTimeOffResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Modules\Booking\Models\PractitionerTimeOff;

class ViewPractitionerTimeOff extends ViewRecord
{
    protected static string $resource = PractitionerTimeOffResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('booking::time_off.sections.request'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('practitioner.full_name')
                                    ->label(__('booking::time_off.fields.staff')),

                                Infolists\Components\TextEntry::make('timeOffType.translated_name')
                                    ->label(__('booking::time_off.fields.time_off_type'))
                                    ->badge()
                                    ->color(fn ($record) => $record->timeOffType?->color ?? 'gray')
                                    ->placeholder(fn ($record) => PractitionerTimeOff::TYPES[$record->type] ?? $record->type),

                                Infolists\Components\TextEntry::make('branch.name')
                                    ->label(__('booking::time_off.fields.branch'))
                                    ->placeholder(__('booking::time_off.all_branches')),

                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('booking::time_off.fields.status'))
                                    ->badge()
                                    ->color(fn (string $state): string => PractitionerTimeOff::STATUS_COLORS[$state] ?? 'gray')
                                    ->formatStateUsing(fn (string $state): string => PractitionerTimeOff::STATUSES[$state] ?? $state),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('booking::time_off.sections.period'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('start_date')
                                    ->label(__('booking::time_off.fields.start_date'))
                                    ->date(),

                                Infolists\Components\TextEntry::make('end_date')
                                    ->label(__('booking::time_off.fields.end_date'))
                                    ->date()
                                    ->visible(fn ($record) => !$record->isHoursBased()),

                                Infolists\Components\TextEntry::make('display_duration')
                                    ->label(__('booking::time_off.fields.duration')),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('is_full_day')
                                    ->label(__('booking::time_off.fields.is_full_day'))
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? __('Yes') : __('No'))
                                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('start_time')
                                    ->label(__('booking::time_off.fields.start_time'))
                                    ->visible(fn ($record) => !$record->is_full_day),

                                Infolists\Components\TextEntry::make('end_time')
                                    ->label(__('booking::time_off.fields.end_time'))
                                    ->visible(fn ($record) => !$record->is_full_day),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('booking::time_off.sections.details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('reason')
                            ->label(__('booking::time_off.fields.reason'))
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('booking::time_off.fields.notes'))
                            ->placeholder('-'),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('approvedBy.full_name')
                                    ->label(__('booking::time_off.fields.approved_by'))
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label(__('Approved At'))
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('booking::time_off.fields.created_at'))
                            ->dateTime(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label(__('booking::time_off.actions.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isPending())
                ->action(function () {
                    if (!$this->record->approve(auth()->id())) {
                        Notification::make()
                            ->title(__('booking::time_off.messages.approve_failed_balance'))
                            ->danger()
                            ->send();

                        return;
                    }
                    Notification::make()
                        ->title(__('booking::time_off.messages.approved'))
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('reject')
                ->label(__('booking::time_off.actions.reject'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('booking::time_off.fields.rejection_reason'))
                        ->required(),
                ])
                ->visible(fn () => $this->record->isPending())
                ->action(function (array $data) {
                    $this->record->reject(auth()->id(), $data['notes']);
                    Notification::make()
                        ->title(__('booking::time_off.messages.rejected'))
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('cancel')
                ->label(__('booking::time_off.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isApproved() || $this->record->isPending())
                ->action(function () {
                    if ($this->record->cancel()) {
                        Notification::make()
                            ->title(__('booking::time_off.messages.cancelled'))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Cannot cancel this request')
                            ->danger()
                            ->send();
                    }
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->isPending()),
        ];
    }
}
