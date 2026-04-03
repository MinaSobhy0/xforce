<?php

namespace Modules\OdooIntegration\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\OdooIntegration\Models\OdooSyncConflict;
use Modules\OdooIntegration\Filament\Pages\ConflictResolution;

class PendingConflictsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): ?string
    {
        return __('odoo-integration::odoo.widgets.pending_conflicts');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OdooSyncConflict::query()
                    ->where('status', 'pending')
                    ->with(['entityMapping', 'syncRecord'])
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('entityMapping.name')
                    ->label(__('odoo-integration::odoo.fields.entity')),

                Tables\Columns\TextColumn::make('conflict_type')
                    ->label(__('odoo-integration::odoo.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => OdooSyncConflict::CONFLICT_TYPES[$state] ?? $state)
                    ->color('warning'),

                Tables\Columns\TextColumn::make('syncRecord.local_id')
                    ->label(__('odoo-integration::odoo.fields.local_id')),

                Tables\Columns\TextColumn::make('syncRecord.odoo_id')
                    ->label(__('odoo-integration::odoo.fields.odoo_id')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('odoo-integration::odoo.fields.detected'))
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label(__('odoo-integration::odoo.actions.resolve'))
                    ->icon('heroicon-o-check')
                    ->url(fn () => ConflictResolution::getUrl()),
            ])
            ->emptyStateHeading(__('odoo-integration::odoo.empty.no_conflicts'))
            ->emptyStateDescription(__('odoo-integration::odoo.empty.no_conflicts_description'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }
}
