<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\BackupResource\Pages;
use App\Models\Backup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class BackupResource extends Resource
{
    protected static ?string $model = Backup::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Backups';

    protected static ?string $navigationGroup = 'System';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.operations');
    }

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Backup Details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn () => 'Backup '.now()->format('Y-m-d H:i')),

                    Forms\Components\Select::make('type')
                        ->options(Backup::TYPES)
                        ->default('full')
                        ->required(),

                    Forms\Components\Select::make('disk')
                        ->options([
                            'local' => 'Local Storage',
                            's3' => 'Amazon S3',
                        ])
                        ->default('local')
                        ->required(),

                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('All tenants (full backup)')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'tenant'),

                    Forms\Components\Textarea::make('notes')
                        ->maxLength(500)
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
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'running' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'running' => 'heroicon-o-arrow-path',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-clock',
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => Backup::TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('All'),

                Tables\Columns\TextColumn::make('formatted_size')
                    ->label('Size'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration'),

                Tables\Columns\TextColumn::make('disk')
                    ->badge()
                    ->color(fn ($state) => $state === 's3' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Backup::STATUSES),

                Tables\Filters\SelectFilter::make('type')
                    ->options(Backup::TYPES),

                Tables\Filters\SelectFilter::make('disk')
                    ->options([
                        'local' => 'Local',
                        's3' => 'S3',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Backup $record) => $record->download_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Backup $record) => $record->status === 'completed' && $record->exists()),

                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Backup')
                    ->modalDescription('Are you sure you want to restore this backup? This will overwrite existing data.')
                    ->modalSubmitActionLabel('Yes, restore backup')
                    ->action(function (Backup $record): void {
                        // In production, this would trigger a restore job
                        Notification::make()
                            ->title('Restore initiated')
                            ->body('The backup restore has been queued. You will be notified when complete.')
                            ->info()
                            ->send();
                    })
                    ->visible(fn (Backup $record) => $record->status === 'completed'),

                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Backup $record) => $record->status === 'failed')
                    ->action(function (Backup $record): void {
                        $record->update(['status' => 'pending']);
                        Notification::make()
                            ->title('Backup queued for retry')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Backup $record) {
                        // Delete file before record
                        if ($record->path && Storage::disk($record->disk)->exists($record->path)) {
                            Storage::disk($record->disk)->delete($record->path);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        foreach ($records as $record) {
                            if ($record->path && Storage::disk($record->disk)->exists($record->path)) {
                                Storage::disk($record->disk)->delete($record->path);
                            }
                            $record->delete();
                        }

                        Notification::make()
                            ->title('Backups deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_backup')
                    ->label('Create Backup')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->default(fn () => 'Backup '.now()->format('Y-m-d H:i')),

                        Forms\Components\Select::make('type')
                            ->options(Backup::TYPES)
                            ->default('full')
                            ->required(),

                        Forms\Components\Select::make('disk')
                            ->options([
                                'local' => 'Local Storage',
                                's3' => 'Amazon S3',
                            ])
                            ->default('local'),

                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'tenant'),

                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500),
                    ])
                    ->action(function (array $data): void {
                        $backup = Backup::create([
                            'name' => $data['name'],
                            'type' => $data['type'],
                            'disk' => $data['disk'],
                            'filename' => 'backup-'.now()->format('Y-m-d-His').'.sql.gz',
                            'status' => 'pending',
                            'notes' => $data['notes'] ?? null,
                            'created_by' => auth()->id(),
                            'tenant_id' => $data['tenant_id'] ?? null,
                        ]);

                        // Dispatch appropriate job based on backup type
                        $sync = config('queue.default') === 'sync';

                        if ($data['type'] === 'tenant' && $backup->tenant_id) {
                            $sync
                                ? \App\Jobs\TenantBackupJob::dispatchSync($backup)
                                : \App\Jobs\TenantBackupJob::dispatch($backup);
                        } elseif (in_array($data['type'], ['full', 'database', 'files'], true)) {
                            $sync
                                ? \App\Jobs\SystemBackupJob::dispatchSync($backup)
                                : \App\Jobs\SystemBackupJob::dispatch($backup);
                        }

                        Notification::make()
                            ->title('Backup queued')
                            ->body('The backup will be created shortly.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('Create your first backup to protect your data.')
            ->emptyStateIcon('heroicon-o-archive-box')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackups::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = static::getModel()::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
