<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\RestoreRequestResource\Pages;
use App\Models\RestoreRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;

class RestoreRequestResource extends Resource
{
    protected static ?string $model = RestoreRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Restore Requests';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Request Details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant')
                        ->relationship('tenant', 'name')
                        ->disabled(),

                    Forms\Components\Select::make('backup_id')
                        ->label('Backup')
                        ->relationship('backup', 'name')
                        ->disabled(),

                    Forms\Components\Select::make('status')
                        ->options(RestoreRequest::STATUSES)
                        ->required(),

                    Forms\Components\Textarea::make('reason')
                        ->label('Reason for Restore')
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Admin Notes')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'pending' => 'heroicon-o-clock',
                        'approved' => 'heroicon-o-check',
                        'rejected' => 'heroicon-o-x-mark',
                        'in_progress' => 'heroicon-o-arrow-path',
                        'completed' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Clinic')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('backup.name')
                    ->label('Backup')
                    ->searchable(),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->reason),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Processed By')
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(RestoreRequest::STATUSES),

                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Restore Request')
                    ->modalDescription('Are you sure you want to approve this restore request? This will allow the restore to proceed.')
                    ->action(function (RestoreRequest $record): void {
                        $record->approve(auth()->id());

                        Notification::make()
                            ->title('Request Approved')
                            ->body('The restore request has been approved.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(RestoreRequest $record) => $record->status === 'pending'),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Restore Request')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Reason for Rejection')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (RestoreRequest $record, array $data): void {
                        $record->reject(auth()->id(), $data['admin_notes']);

                        Notification::make()
                            ->title('Request Rejected')
                            ->body('The restore request has been rejected.')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn(RestoreRequest $record) => $record->status === 'pending'),

                Tables\Actions\Action::make('start_restore')
                    ->label('Start Restore')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Start Restore Process')
                    ->modalDescription('This will initiate the backup restore process. The tenant\'s current data will be replaced with the backup data.')
                    ->action(function (RestoreRequest $record): void {
                        // Dispatch the restore job
                        if (config('queue.default') === 'sync') {
                            \App\Jobs\TenantRestoreJob::dispatchSync($record);
                        } else {
                            \App\Jobs\TenantRestoreJob::dispatch($record);
                        }

                        Notification::make()
                            ->title('Restore Started')
                            ->body('The restore process has been initiated. You will be notified when complete.')
                            ->info()
                            ->send();
                    })
                    ->visible(fn(RestoreRequest $record) => $record->status === 'approved'),

                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (RestoreRequest $record): void {
                        $record->markCompleted();

                        Notification::make()
                            ->title('Restore Completed')
                            ->body('The restore has been marked as completed.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(RestoreRequest $record) => $record->status === 'in_progress'),

                Tables\Actions\Action::make('mark_failed')
                    ->label('Mark Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Failure Reason')
                            ->required(),
                    ])
                    ->action(function (RestoreRequest $record, array $data): void {
                        $record->markFailed($data['admin_notes']);

                        Notification::make()
                            ->title('Restore Failed')
                            ->body('The restore has been marked as failed.')
                            ->danger()
                            ->send();
                    })
                    ->visible(fn(RestoreRequest $record) => $record->status === 'in_progress'),

                Tables\Actions\ViewAction::make(),
            ])
            ->emptyStateHeading('No restore requests')
            ->emptyStateDescription('Restore requests from tenants will appear here.')
            ->emptyStateIcon('heroicon-o-arrow-uturn-left')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestoreRequests::route('/'),
            'view' => Pages\ViewRestoreRequest::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
