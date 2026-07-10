<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformEmailListResource\Pages;
use App\Filament\SuperAdmin\Resources\PlatformEmailListResource\RelationManagers;
use App\Models\PlatformEmailList;
use App\Models\PlatformEmailListMember;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformEmailListResource extends Resource
{
    protected static ?string $model = PlatformEmailList::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 10;

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.platform_email');
    }

    protected static ?string $modelLabel = 'Email List';

    protected static ?string $pluralModelLabel = 'Email Lists';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),

            Forms\Components\Textarea::make('description')->rows(2)->maxLength(500),

            Forms\Components\Select::make('kind')
                ->options(PlatformEmailList::KINDS)
                ->default(PlatformEmailList::KIND_MANUAL)
                ->required()
                ->live(),

            Forms\Components\Toggle::make('is_active')->default(true),

            Forms\Components\Section::make('Dynamic source')
                ->visible(fn (Forms\Get $get) => $get('kind') === PlatformEmailList::KIND_DYNAMIC)
                ->schema([
                    Forms\Components\Select::make('dynamic_source')
                        ->options([
                            'owner_users_active' => 'Owner-users of active tenants',
                            'contact_inquiries' => 'Contact-form inquiries',
                            'event_registrations' => 'Event registrations',
                        ])
                        ->required(),
                    Forms\Components\KeyValue::make('dynamic_filters')
                        ->helperText('Optional per-source filters (e.g. status=new).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('kind')
                    ->formatStateUsing(fn ($state) => PlatformEmailList::KINDS[$state] ?? $state)
                    ->colors([
                        'success' => PlatformEmailList::KIND_MANUAL,
                        'info' => PlatformEmailList::KIND_DYNAMIC,
                    ]),
                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members')
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_members_count')
                    ->counts('activeMembers')
                    ->label('Subscribed'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->options(PlatformEmailList::KINDS),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformEmailLists::route('/'),
            'create' => Pages\CreatePlatformEmailList::route('/create'),
            'edit' => Pages\EditPlatformEmailList::route('/{record}/edit'),
        ];
    }
}
