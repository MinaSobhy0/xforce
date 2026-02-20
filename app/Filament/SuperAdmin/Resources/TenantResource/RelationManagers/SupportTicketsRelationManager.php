<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\RelationManagers;

use App\Models\SupportTicket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class SupportTicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'supportTickets';

    protected static ?string $title = 'Support Tickets';

    protected static ?string $icon = 'heroicon-o-ticket';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ticket Details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('ticket_number')
                            ->label('Ticket #'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'open' => 'warning',
                                'in_progress' => 'info',
                                'waiting_customer' => 'gray',
                                'resolved' => 'success',
                                'closed' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('subject')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('priority')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'urgent' => 'danger',
                                'high' => 'warning',
                                'normal' => 'info',
                                'low' => 'gray',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('category')
                            ->formatStateUsing(fn($state) => SupportTicket::CATEGORIES[$state] ?? $state),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->html(),

                        Infolists\Components\TextEntry::make('reporter_name')
                            ->label('Reporter'),

                        Infolists\Components\TextEntry::make('reporter_email')
                            ->label('Email')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Opened')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label('Resolved')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),

                Infolists\Components\Section::make('Resolution')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('resolution_notes')
                            ->label('')
                            ->placeholder('No resolution notes yet')
                            ->html(),
                    ]),

                Infolists\Components\Section::make('Internal Notes')
                    ->collapsed()
                    ->schema([
                        Infolists\Components\TextEntry::make('internal_notes')
                            ->label('')
                            ->placeholder('No internal notes')
                            ->html(),
                    ]),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('#')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        'low' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'waiting_customer' => 'gray',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Opened')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => in_array($record->status, ['open', 'in_progress', 'waiting_customer']))
                    ->form([
                        Forms\Components\Textarea::make('resolution_notes')
                            ->label('Resolution Notes'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->resolve($data['resolution_notes'] ?? null);
                        \Filament\Notifications\Notification::make()
                            ->title('Ticket resolved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
            ]);
    }
}
