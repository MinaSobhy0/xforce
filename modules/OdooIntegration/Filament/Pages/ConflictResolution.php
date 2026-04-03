<?php

namespace Modules\OdooIntegration\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Modules\OdooIntegration\Models\OdooSyncConflict;
use Modules\OdooIntegration\Enums\ConflictStatus;
use Modules\OdooIntegration\Services\Sync\ConflictResolver;

class ConflictResolution extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 92;

    protected static string $view = 'odoo-integration::filament.pages.conflict-resolution';

    public function getTitle(): string
    {
        return __('odoo-integration::odoo.pages.conflicts');
    }

    public static function getNavigationLabel(): string
    {
        return __('odoo-integration::odoo.pages.conflicts');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = OdooSyncConflict::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OdooSyncConflict::query()
                    ->with(['entityMapping', 'syncRecord', 'resolvedByUser'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('entityMapping.name')
                    ->label(__('odoo-integration::odoo.fields.entity'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('conflict_type')
                    ->label(__('odoo-integration::odoo.fields.conflict_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => OdooSyncConflict::CONFLICT_TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        OdooSyncConflict::TYPE_BOTH_MODIFIED => 'warning',
                        OdooSyncConflict::TYPE_DELETED_LOCALLY => 'danger',
                        OdooSyncConflict::TYPE_DELETED_REMOTELY => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('syncRecord.local_id')
                    ->label(__('odoo-integration::odoo.fields.local_id')),

                Tables\Columns\TextColumn::make('syncRecord.odoo_id')
                    ->label(__('odoo-integration::odoo.fields.odoo_id')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('odoo-integration::odoo.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ConflictStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof ConflictStatus ? $state->color() : 'gray'),

                Tables\Columns\TextColumn::make('resolution')
                    ->label(__('odoo-integration::odoo.fields.resolution'))
                    ->formatStateUsing(fn ($state) => $state ? (OdooSyncConflict::RESOLUTIONS[$state] ?? $state) : '-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('resolvedByUser.name')
                    ->label(__('odoo-integration::odoo.fields.resolved_by'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('odoo-integration::odoo.fields.detected_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('odoo-integration::odoo.fields.status'))
                    ->options(ConflictStatus::options())
                    ->default(ConflictStatus::PENDING->value),

                Tables\Filters\SelectFilter::make('conflict_type')
                    ->label(__('odoo-integration::odoo.fields.conflict_type'))
                    ->options(OdooSyncConflict::CONFLICT_TYPES),

                Tables\Filters\SelectFilter::make('entity_mapping_id')
                    ->label(__('odoo-integration::odoo.fields.entity'))
                    ->relationship('entityMapping', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_diff')
                    ->label(__('odoo-integration::odoo.actions.view_diff'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->modalContent(fn (OdooSyncConflict $record) => view('odoo-integration::filament.components.conflict-diff', [
                        'conflict' => $record,
                    ]))
                    ->modalHeading(__('odoo-integration::odoo.modals.conflict_diff_title'))
                    ->modalWidth('7xl'),

                Tables\Actions\Action::make('keep_local')
                    ->label(__('odoo-integration::odoo.actions.keep_local'))
                    ->icon('heroicon-o-computer-desktop')
                    ->color('primary')
                    ->visible(fn (OdooSyncConflict $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('odoo-integration::odoo.fields.notes'))
                            ->rows(2),
                    ])
                    ->action(function (OdooSyncConflict $record, array $data) {
                        $resolver = app(ConflictResolver::class);
                        $resolver->keepLocal($record, auth()->id(), $data['notes'] ?? null);

                        Notification::make()
                            ->title(__('odoo-integration::odoo.messages.conflict_resolved'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('keep_odoo')
                    ->label(__('odoo-integration::odoo.actions.keep_odoo'))
                    ->icon('heroicon-o-cloud')
                    ->color('warning')
                    ->visible(fn (OdooSyncConflict $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('odoo-integration::odoo.fields.notes'))
                            ->rows(2),
                    ])
                    ->action(function (OdooSyncConflict $record, array $data) {
                        $resolver = app(ConflictResolver::class);
                        $resolver->keepOdoo($record, auth()->id(), $data['notes'] ?? null);

                        Notification::make()
                            ->title(__('odoo-integration::odoo.messages.conflict_resolved'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('dismiss')
                    ->label(__('odoo-integration::odoo.actions.dismiss'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (OdooSyncConflict $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('odoo-integration::odoo.fields.notes'))
                            ->rows(2),
                    ])
                    ->action(function (OdooSyncConflict $record, array $data) {
                        $resolver = app(ConflictResolver::class);
                        $resolver->skip($record, auth()->id(), $data['notes'] ?? null);

                        Notification::make()
                            ->title(__('odoo-integration::odoo.messages.conflict_dismissed'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('dismiss_all')
                    ->label(__('odoo-integration::odoo.actions.dismiss_all'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $resolver = app(ConflictResolver::class);
                        foreach ($records as $record) {
                            if ($record->isPending()) {
                                $resolver->skip($record, auth()->id(), 'Bulk dismissed');
                            }
                        }

                        Notification::make()
                            ->title(__('odoo-integration::odoo.messages.conflicts_dismissed'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
