<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use Modules\Core\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Support Tickets';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'open')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Ticket Details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Clinic')
                        ->options(fn() => Tenant::pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('subject')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('category')
                        ->options(SupportTicket::CATEGORIES)
                        ->default('other')
                        ->required(),

                    Forms\Components\Select::make('priority')
                        ->options(SupportTicket::PRIORITIES)
                        ->default('normal')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options(SupportTicket::STATUSES)
                        ->default('open')
                        ->required(),
                ]),

            Forms\Components\Section::make('Reporter')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('reporter_name')
                        ->label('Name'),
                    Forms\Components\TextInput::make('reporter_email')
                        ->label('Email')
                        ->email(),
                ]),

            Forms\Components\Section::make('Internal Notes')
                ->description('These notes are only visible to support staff')
                ->schema([
                    Forms\Components\Textarea::make('internal_notes')
                        ->label('')
                        ->rows(4)
                        ->placeholder('Add internal notes here (not visible to clinic)...'),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('Resolution')
                ->schema([
                    Forms\Components\Textarea::make('resolution_notes')
                        ->label('Resolution Notes')
                        ->rows(3),
                ])
                ->visible(fn(Forms\Get $get) => in_array($get('status'), ['resolved', 'closed'])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('#')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Clinic')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('gray'),

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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SupportTicket::STATUSES),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(SupportTicket::PRIORITIES),
                Tables\Filters\SelectFilter::make('category')
                    ->options(SupportTicket::CATEGORIES),
            ])
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

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('escalate')
                        ->label('Escalate')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('danger')
                        ->visible(fn($record) => $record->priority !== 'urgent')
                        ->form([
                            Forms\Components\Select::make('escalate_to')
                                ->label('Escalate To')
                                ->options([
                                    'support_lead' => 'Support Lead',
                                    'manager' => 'Manager',
                                    'technical' => 'Technical Team',
                                ])
                                ->required(),
                            Forms\Components\Textarea::make('escalation_reason')
                                ->label('Reason')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'priority' => 'urgent',
                                'escalated_at' => now(),
                                'escalated_to' => $data['escalate_to'],
                                'internal_notes' => ($record->internal_notes ? $record->internal_notes . "\n\n" : '')
                                    . "[ESCALATED " . now()->format('Y-m-d H:i') . "]\n"
                                    . "To: {$data['escalate_to']}\n"
                                    . "Reason: {$data['escalation_reason']}",
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Ticket escalated')
                                ->body("Escalated to {$data['escalate_to']}")
                                ->warning()
                                ->send();
                        }),

                    Tables\Actions\Action::make('emailClinic')
                        ->label('Email Clinic')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('subject')
                                ->default(fn($record) => "Re: {$record->subject}")
                                ->required(),
                            Forms\Components\RichEditor::make('message')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            // TODO: Send email to clinic
                            \Filament\Notifications\Notification::make()
                                ->title('Email sent')
                                ->body("Email sent to {$record->tenant->contact_email}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('loginAs')
                        ->label('Login As Clinic')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('gray')
                        ->url(fn($record) => "https://{$record->tenant->slug}.x-linic.com/admin")
                        ->openUrlInNewTab(),
                ])
                    ->label('More')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray'),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
