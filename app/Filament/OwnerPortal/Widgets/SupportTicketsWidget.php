<?php

namespace App\Filament\OwnerPortal\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;

class SupportTicketsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'My Support Tickets';

    public function table(Table $table): Table
    {
        $tenantId = Auth::user()?->tenant_id;

        return $table
            ->query(
                SupportTicket::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['open', 'in_progress'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')
                    ->label('Ticket #'),

                Tables\Columns\TextColumn::make('subject')
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Opened')
                    ->since(),
            ])
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(SupportTicket $record) => route('filament.admin.resources.my-support-tickets.view', $record)),
            ])
            ->emptyStateHeading('No open tickets')
            ->emptyStateDescription('You have no open support tickets.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
