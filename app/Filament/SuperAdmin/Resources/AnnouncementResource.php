<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Resources\Concerns\Translatable;

class AnnouncementResource extends Resource
{
    use Translatable;

    protected static ?string $model = Announcement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Announcements';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Content')
                ->columns(1)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\RichEditor::make('body')
                        ->label('Body')
                        ->required(),
                ]),

            Forms\Components\Section::make('Settings')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options(Announcement::TYPES)
                        ->default('info')
                        ->required(),

                    Forms\Components\Select::make('delivery_method')
                        ->options(Announcement::DELIVERY_METHODS)
                        ->default('in_app')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options(Announcement::STATUSES)
                        ->default('draft')
                        ->required()
                        ->live(),

                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Schedule For')
                        ->visible(fn(Forms\Get $get) => $get('status') === 'scheduled'),

                    Forms\Components\CheckboxList::make('target_plans')
                        ->label('Target Plans')
                        ->options(fn() => SubscriptionPlan::active()->pluck('code', 'id'))
                        ->columns(3)
                        ->helperText('Leave empty for all plans'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn($state) => Announcement::TYPES[$state] ?? $state)
                    ->color(fn(string $state) => match ($state) {
                        'info' => 'info',
                        'feature' => 'success',
                        'maintenance' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->limit(50),

                Tables\Columns\TextColumn::make('delivery_method')
                    ->label('Delivery')
                    ->formatStateUsing(fn($state) => Announcement::DELIVERY_METHODS[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'warning',
                        'sent' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('read_count')
                    ->label('Read')
                    ->numeric(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Announcement::STATUSES),

                Tables\Filters\SelectFilter::make('type')
                    ->options(Announcement::TYPES),
            ])
            ->actions([
                Tables\Actions\Action::make('send')
                    ->label('Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Announcement')
                    ->modalDescription('This will send the announcement to all targeted clinics immediately.')
                    ->visible(fn($record) => $record->status !== 'sent')
                    ->action(function ($record) {
                        $record->send();
                        \Filament\Notifications\Notification::make()
                            ->title('Announcement sent')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn($record) => $record->title)
                    ->modalContent(fn($record) => view('filament.super-admin.modals.announcement-preview', ['record' => $record])),

                Tables\Actions\Action::make('viewStats')
                    ->label('View Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->visible(fn($record) => $record->status === 'sent')
                    ->modalHeading(fn($record) => 'Analytics: ' . $record->title)
                    ->modalWidth('lg')
                    ->modalContent(function ($record) {
                        $totalSent = $record->sent_count ?? 0;
                        $totalRead = $record->read_count ?? 0;
                        $readRate = $totalSent > 0 ? round(($totalRead / $totalSent) * 100, 1) : 0;

                        return view('filament.super-admin.modals.announcement-stats', [
                            'record' => $record,
                            'totalSent' => $totalSent,
                            'totalRead' => $totalRead,
                            'readRate' => $readRate,
                        ]);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->status === 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
