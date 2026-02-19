<?php

namespace App\Filament\SuperAdmin\Widgets;

use Modules\Core\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSignupsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Recent Signups';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Clinic')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since(),
            ])
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Tenant $record) => route('filament.super-admin.resources.tenants.view', $record)),
            ]);
    }
}
