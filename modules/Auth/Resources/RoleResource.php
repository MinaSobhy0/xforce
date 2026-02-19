<?php

namespace Modules\Auth\Resources;

use XLinic\Framework\Core\Filament\BaseResource;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Modules\Auth\Resources\RoleResource\Pages;
use Modules\Auth\Resources\RoleResource\RelationManagers;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class RoleResource extends BaseResource
{
    protected static ?string $model = Role::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 20;
    protected static ?string $moduleCode = 'auth';

    public static function getNavigationLabel(): string
    {
        return __('Roles & Permissions');
    }

    public static function getModelLabel(): string
    {
        return __('Role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Roles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Role Information'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Role Name'))
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                        if ($context === 'create') {
                                            $set('display_name', ucwords(str_replace('_', ' ', $state)));
                                        }
                                    }),

                                Forms\Components\TextInput::make('display_name')
                                    ->label(__('Display Name'))
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label(__('Description'))
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_system')
                                    ->label(__('System Role'))
                                    ->helperText(__('System roles cannot be deleted'))
                                    ->default(false)
                                    ->disabled(fn ($record) => $record?->is_system ?? false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('Active'))
                                    ->default(true),

                                Forms\Components\TextInput::make('level')
                                    ->label(__('Priority Level'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->helperText(__('Higher numbers have more priority')),
                            ]),
                    ]),

                Forms\Components\Section::make(__('Permissions'))
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship('permissions', 'display_name')
                            ->options(function () {
                                return Permission::query()
                                    ->orderBy('module')
                                    ->orderBy('name')
                                    ->get()
                                    ->groupBy('module')
                                    ->map(function ($permissions, $module) {
                                        return $permissions->pluck('display_name', 'id')->toArray();
                                    })
                                    ->toArray();
                            })
                            ->columns(2)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->searchable(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('Display Name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('System Name'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('Users'))
                    ->counts('users')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('Permissions'))
                    ->counts('permissions')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('level')
                    ->label(__('Level'))
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 90 => 'danger',
                        $state >= 70 => 'warning',
                        $state >= 50 => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('System'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Filters\TernaryFilter::make('is_system')
                    ->label(__('System Role'))
                    ->boolean(),

                Tables\Filters\Filter::make('has_users')
                    ->label(__('Has Users'))
                    ->query(fn (Builder $query): Builder => $query->has('users')),

                Tables\Filters\SelectFilter::make('level')
                    ->label(__('Priority Level'))
                    ->options([
                        '90-100' => __('Critical (90-100)'),
                        '70-89' => __('High (70-89)'),
                        '50-69' => __('Medium (50-69)'),
                        '0-49' => __('Low (0-49)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            '90-100' => $query->whereBetween('level', [90, 100]),
                            '70-89' => $query->whereBetween('level', [70, 89]),
                            '50-69' => $query->whereBetween('level', [50, 69]),
                            '0-49' => $query->whereBetween('level', [0, 49]),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('duplicate')
                    ->label(__('Duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label(__('New Role Name'))
                            ->required()
                            ->unique('roles', 'name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('display_name')
                            ->label(__('Display Name'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (Role $record, array $data) {
                        $newRole = $record->replicate();
                        $newRole->name = $data['name'];
                        $newRole->display_name = $data['display_name'];
                        $newRole->is_system = false;
                        $newRole->save();

                        // Copy permissions
                        $newRole->permissions()->sync($record->permissions->pluck('id'));

                        return redirect()->to(static::getUrl('edit', ['record' => $newRole]));
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Role $record) => !$record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if (!$record->is_system) {
                                    $record->delete();
                                }
                            });
                        }),

                    Tables\Actions\BulkAction::make('activate')
                        ->label(__('Activate'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label(__('Deactivate'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(function ($record) {
                            if (!$record->is_system) {
                                $record->update(['is_active' => false]);
                            }
                        })),
                ]),
            ])
            ->defaultSort('level', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('Role Information'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('display_name')
                                    ->label(__('Display Name'))
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('name')
                                    ->label(__('System Name'))
                                    ->badge(),

                                Infolists\Components\TextEntry::make('level')
                                    ->label(__('Priority Level'))
                                    ->badge()
                                    ->color(fn ($state) => match (true) {
                                        $state >= 90 => 'danger',
                                        $state >= 70 => 'warning',
                                        $state >= 50 => 'info',
                                        default => 'gray',
                                    }),

                                Infolists\Components\IconEntry::make('is_active')
                                    ->label(__('Active'))
                                    ->boolean(),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->label(__('Description'))
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make(__('Statistics'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('users_count')
                                    ->label(__('Total Users'))
                                    ->state(fn ($record) => $record->users()->count())
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('permissions_count')
                                    ->label(__('Total Permissions'))
                                    ->state(fn ($record) => $record->permissions()->count())
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('active_users_count')
                                    ->label(__('Active Users'))
                                    ->state(fn ($record) => $record->users()->where('is_active', true)->count())
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\PermissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    protected static ?string $slug = 'roles';
}