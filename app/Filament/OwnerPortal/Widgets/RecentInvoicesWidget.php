<?php

namespace App\Filament\OwnerPortal\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\PlatformInvoice;
use Illuminate\Support\Facades\Auth;

class RecentInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Recent Invoices';

    public function table(Table $table): Table
    {
        $tenantId = Auth::user()?->tenant_id;

        return $table
            ->query(
                PlatformInvoice::query()
                    ->where('tenant_id', $tenantId)
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn($state) => 'EGP ' . number_format($state / 100, 2)),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->date(),
            ])
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn(PlatformInvoice $record) => route('filament.admin.resources.my-invoices.view', $record)),
            ]);
    }
}
