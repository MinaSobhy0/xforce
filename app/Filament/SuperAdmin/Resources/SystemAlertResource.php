<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\SystemAlertResource\Pages;
use App\Models\SystemAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class SystemAlertResource extends Resource
{
    protected static ?string $model = SystemAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'System Alerts';

    protected static ?string $navigationGroup = 'System';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.health');
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $critical = SystemAlert::unresolved()->critical()->count();
        $warning = SystemAlert::unresolved()->warning()->count();
        $total = $critical + $warning;

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        if (SystemAlert::unresolved()->critical()->exists()) {
            return 'danger';
        }
        if (SystemAlert::unresolved()->warning()->exists()) {
            return 'warning';
        }
        return null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Alert Information')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options(SystemAlert::TYPES)
                        ->required(),

                    Forms\Components\Select::make('severity')
                        ->options(SystemAlert::SEVERITIES)
                        ->required(),

                    Forms\Components\Select::make('source')
                        ->options(SystemAlert::SOURCES)
                        ->default('system')
                        ->required(),

                    Forms\Components\Select::make('tenant_id')
                        ->label('Related Tenant')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('All / Platform-wide'),

                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('message')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Resolution')
                ->visible(fn($record) => $record && $record->is_resolved)
                ->schema([
                    Forms\Components\Placeholder::make('resolved_info')
                        ->label('')
                        ->content(fn($record) => "Resolved by {$record->resolvedByUser?->name} on {$record->resolved_at?->format('M j, Y g:i A')}"),

                    Forms\Components\Textarea::make('resolution_notes')
                        ->label('Resolution Notes')
                        ->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->limit(50),

                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn($state) => SystemAlert::TYPES[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('source')
                    ->formatStateUsing(fn($state) => SystemAlert::SOURCES[$state] ?? $state),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('Platform')
                    ->limit(20),

                Tables\Columns\IconColumn::make('is_resolved')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->options(SystemAlert::SEVERITIES),

                Tables\Filters\SelectFilter::make('type')
                    ->options(SystemAlert::TYPES),

                Tables\Filters\TernaryFilter::make('is_resolved')
                    ->label('Status')
                    ->trueLabel('Resolved')
                    ->falseLabel('Unresolved'),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->rows(3),
                    ])
                    ->visible(fn($record) => !$record->is_resolved)
                    ->action(function ($record, array $data) {
                        $record->resolve(auth()->id(), $data['resolution_notes'] ?? null);
                        \Filament\Notifications\Notification::make()
                            ->title('Alert resolved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('resolve_all')
                    ->label('Resolve Selected')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $records->each(fn($record) => $record->resolve(auth()->id()));
                        \Filament\Notifications\Notification::make()
                            ->title('Alerts resolved')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Alert Details')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('severity')
                        ->badge()
                        ->color(fn(string $state) => match ($state) {
                            'critical' => 'danger',
                            'warning' => 'warning',
                            'info' => 'info',
                            default => 'gray',
                        }),

                    Infolists\Components\TextEntry::make('type')
                        ->formatStateUsing(fn($state) => SystemAlert::TYPES[$state] ?? $state),

                    Infolists\Components\TextEntry::make('source')
                        ->formatStateUsing(fn($state) => SystemAlert::SOURCES[$state] ?? $state),

                    Infolists\Components\TextEntry::make('title')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('message')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('tenant.name')
                        ->label('Related Tenant')
                        ->placeholder('Platform-wide'),

                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Created')
                        ->dateTime(),
                ]),

            Infolists\Components\Section::make('Resolution')
                ->visible(fn($record) => $record->is_resolved)
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('resolvedByUser.name')
                        ->label('Resolved By'),

                    Infolists\Components\TextEntry::make('resolved_at')
                        ->label('Resolved At')
                        ->dateTime(),

                    Infolists\Components\TextEntry::make('resolution_notes')
                        ->columnSpanFull()
                        ->placeholder('No notes'),
                ]),

            Infolists\Components\Section::make('Metadata')
                ->visible(fn($record) => !empty($record->metadata))
                ->schema([
                    Infolists\Components\KeyValueEntry::make('metadata'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemAlerts::route('/'),
            'view' => Pages\ViewSystemAlert::route('/{record}'),
        ];
    }
}
