<?php

namespace App\Filament\OwnerPortal\Resources\MySupportTicketResource\Pages;

use App\Filament\OwnerPortal\Resources\MySupportTicketResource;
use App\Models\SupportTicketReply;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ViewMySupportTicket extends BaseViewRecord
{
    protected static string $resource = MySupportTicketResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Ticket Information')
                    ->columns(4)
                    ->schema([
                        Components\TextEntry::make('ticket_number')
                            ->label('Ticket #'),

                        Components\TextEntry::make('category')
                            ->badge(),

                        Components\TextEntry::make('priority')
                            ->badge()
                            ->color(fn(string $state) => match ($state) {
                                'urgent' => 'danger',
                                'high' => 'warning',
                                'normal' => 'info',
                                'low' => 'gray',
                                default => 'gray',
                            }),

                        Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state) => match ($state) {
                                'open' => 'warning',
                                'in_progress' => 'info',
                                'resolved' => 'success',
                                'closed' => 'gray',
                                default => 'gray',
                            }),
                    ]),

                Components\Section::make('Details')
                    ->schema([
                        Components\TextEntry::make('subject')
                            ->columnSpanFull(),

                        Components\TextEntry::make('description')
                            ->html()
                            ->columnSpanFull(),

                        Components\TextEntry::make('created_at')
                            ->label('Opened')
                            ->dateTime(),

                        Components\TextEntry::make('resolved_at')
                            ->label('Resolved')
                            ->dateTime()
                            ->placeholder('Not yet resolved'),
                    ]),

                Components\Section::make('Conversation')
                    ->schema([
                        Components\RepeatableEntry::make('replies')
                            ->label('')
                            ->schema([
                                Components\TextEntry::make('user_name')
                                    ->label('From')
                                    ->weight('bold'),

                                Components\TextEntry::make('created_at')
                                    ->label('')
                                    ->since(),

                                Components\TextEntry::make('message')
                                    ->label('')
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->contained(false),
                    ]),
            ]);
    }

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label('Add Reply')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('primary')
                ->form([
                    Forms\Components\RichEditor::make('message')
                        ->label('Your Reply')
                        ->required(),
                ])
                ->action(function (array $data) {
                    SupportTicketReply::create([
                        'ticket_id' => $this->record->id,
                        'user_id' => Auth::id(),
                        'user_name' => Auth::user()->name,
                        'message' => $data['message'],
                        'is_internal' => false,
                    ]);

                    // Reopen ticket if it was resolved
                    if ($this->record->status === 'resolved') {
                        $this->record->update(['status' => 'open']);
                    }

                    Notification::make()
                        ->title('Reply sent')
                        ->success()
                        ->send();

                    $this->refreshFormData(['replies']);
                })
                ->visible(fn() => !in_array($this->record->status, ['closed'])),

            Actions\Action::make('close')
                ->label('Close Ticket')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update([
                        'status' => 'closed',
                        'resolved_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Ticket closed')
                        ->success()
                        ->send();
                })
                ->visible(fn() => in_array($this->record->status, ['resolved'])),
        ];
    }
}
