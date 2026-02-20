<?php

namespace App\Filament\OwnerPortal\Pages;

use App\Models\Backup;
use App\Models\RestoreRequest;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class MyBackups extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Backups';

    protected static ?string $navigationGroup = 'Account';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.owner-portal.pages.my-backups';

    public function getTitle(): string
    {
        return 'My Backups';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'running' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'running' => 'heroicon-o-arrow-path',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-clock',
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => Backup::TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('formatted_size')
                    ->label('Size'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Backup::STATUSES),
            ])
            ->actions([
                Tables\Actions\Action::make('request_restore')
                    ->label('Request Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Request Backup Restore')
                    ->modalDescription('Submit a restore request. A platform administrator will review and approve your request.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Restore')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Please explain why you need to restore this backup...'),
                    ])
                    ->action(function (Backup $record, array $data): void {
                        // Check if there's already a pending request for this backup
                        $existingRequest = RestoreRequest::where('backup_id', $record->id)
                            ->where('tenant_id', $record->tenant_id)
                            ->whereIn('status', ['pending', 'approved', 'in_progress'])
                            ->first();

                        if ($existingRequest) {
                            Notification::make()
                                ->title('Request Already Exists')
                                ->body('There is already a pending or in-progress restore request for this backup.')
                                ->warning()
                                ->send();
                            return;
                        }

                        RestoreRequest::create([
                            'tenant_id' => $record->tenant_id,
                            'backup_id' => $record->id,
                            'requested_by' => auth()->id(),
                            'status' => 'pending',
                            'reason' => $data['reason'],
                            'requested_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Restore Request Submitted')
                            ->body('Your restore request has been submitted. You will be notified once it is reviewed.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(Backup $record) => $record->status === 'completed'),
            ])
            ->emptyStateHeading('No backups available')
            ->emptyStateDescription('Your clinic backups will appear here.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    protected function getTableQuery(): Builder
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return Backup::query()->whereRaw('1 = 0'); // Return empty query
        }

        return Backup::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\OwnerPortal\Widgets\RestoreRequestsWidget::class,
        ];
    }
}
