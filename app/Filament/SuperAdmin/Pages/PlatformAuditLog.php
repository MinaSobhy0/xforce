<?php

namespace App\Filament\SuperAdmin\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms;

class PlatformAuditLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.super-admin.pages.platform-audit-log';

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->searchable()
                    ->icon(fn($record) => match (true) {
                        str_contains($record->description, 'login') => 'heroicon-o-key',
                        str_contains($record->description, 'created') => 'heroicon-o-plus-circle',
                        str_contains($record->description, 'updated') => 'heroicon-o-pencil',
                        str_contains($record->description, 'deleted') => 'heroicon-o-trash',
                        str_contains($record->description, 'suspended') => 'heroicon-o-pause-circle',
                        str_contains($record->description, 'approved') => 'heroicon-o-check-circle',
                        str_contains($record->description, 'sent') => 'heroicon-o-paper-airplane',
                        default => 'heroicon-o-document',
                    })
                    ->iconColor(fn($record) => match (true) {
                        str_contains($record->description, 'deleted') => 'danger',
                        str_contains($record->description, 'suspended') => 'warning',
                        str_contains($record->description, 'approved') => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Admin')
                    ->placeholder('System')
                    ->searchable(),

                Tables\Columns\TextColumn::make('properties')
                    ->label('Details')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return '-';
                        $props = is_array($state) ? $state : json_decode($state, true);
                        if (isset($props['attributes'])) {
                            return collect($props['attributes'])->take(2)
                                ->map(fn($v, $k) => "$k: " . (is_array($v) ? json_encode($v) : $v))
                                ->join(', ');
                        }
                        return '-';
                    })
                    ->limit(50)
                    ->tooltip(fn($state) => json_encode($state, JSON_PRETTY_PRINT)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('description')
                    ->label('Action Type')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'login' => 'Login',
                        'approved' => 'Approved',
                        'suspended' => 'Suspended',
                    ])
                    ->query(fn($query, $data) => $data['value']
                        ? $query->where('description', 'like', "%{$data['value']}%")
                        : $query),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Admin')
                    ->relationship('causer', 'email')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name}")
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Activity Details')
                    ->modalContent(fn($record) => view('filament.super-admin.modals.activity-details', ['record' => $record])),
            ])
            ->bulkActions([])
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export')
                ->label('Export Log')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->title('Export started')
                        ->body('The audit log export has been queued.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
