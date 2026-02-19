<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\RelationManagers;

use App\Models\TenantActivityLog;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Activity Log';

    protected static ?string $icon = 'heroicon-o-clock';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => TenantActivityLog::EVENT_TYPES[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn(string $state) => match ($state) {
                        'payment_received', 'reactivated' => 'success',
                        'payment_failed', 'suspended' => 'danger',
                        'plan_upgraded', 'addon_activated' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->limit(100),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25]);
    }
}
