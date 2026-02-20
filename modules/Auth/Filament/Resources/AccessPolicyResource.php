<?php

namespace Modules\Auth\Filament\Resources;

use Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;
use Modules\Auth\Models\AccessPolicy;
use Modules\Auth\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccessPolicyResource extends Resource
{
    protected static ?string $model = AccessPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('auth::auth.access_policies');
    }

    public static function getModelLabel(): string
    {
        return __('auth::auth.access_policy');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auth::auth.access_policies');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('auth::auth.policy_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('auth::auth.policy_name'))
                            ->required()
                            ->maxLength(100)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('model_type')
                            ->label(__('auth::auth.model_type'))
                            ->required()
                            ->placeholder('Modules\\Patients\\Models\\Patient')
                            ->helperText(__('auth::auth.model_type_help'))
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->label(__('auth::auth.description'))
                            ->rows(2)
                            ->columnSpan(2),

                        Forms\Components\Select::make('role_id')
                            ->label(__('auth::auth.role'))
                            ->relationship('role', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Forms\Get $get) => $get('apply_to_all_roles'))
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('apply_to_all_roles')
                            ->label(__('auth::auth.apply_to_all_roles'))
                            ->helperText(__('auth::auth.apply_to_all_roles_help'))
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('role_id', null))
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('priority')
                            ->label(__('auth::auth.priority'))
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->maxValue(100)
                            ->helperText(__('auth::auth.priority_help'))
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('auth::auth.active'))
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('auth::auth.permissions'))
                    ->schema([
                        Forms\Components\Toggle::make('perm_read')
                            ->label(__('auth::auth.perm_read'))
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Toggle::make('perm_create')
                            ->label(__('auth::auth.perm_create'))
                            ->default(false)
                            ->inline(false),

                        Forms\Components\Toggle::make('perm_update')
                            ->label(__('auth::auth.perm_update'))
                            ->default(false)
                            ->inline(false),

                        Forms\Components\Toggle::make('perm_delete')
                            ->label(__('auth::auth.perm_delete'))
                            ->default(false)
                            ->inline(false),
                    ])
                    ->columns(4),

                Forms\Components\Section::make(__('auth::auth.domain_filter'))
                    ->schema([
                        Forms\Components\Repeater::make('domain_filter')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('field')
                                    ->label(__('auth::auth.field'))
                                    ->required()
                                    ->placeholder('branch_id'),

                                Forms\Components\Select::make('operator')
                                    ->label(__('auth::auth.operator'))
                                    ->options([
                                        '=' => 'Equals (=)',
                                        '!=' => 'Not Equals (!=)',
                                        '>' => 'Greater Than (>)',
                                        '>=' => 'Greater Than or Equal (>=)',
                                        '<' => 'Less Than (<)',
                                        '<=' => 'Less Than or Equal (<=)',
                                        'in' => 'In List',
                                        'not in' => 'Not In List',
                                        'like' => 'Contains (LIKE)',
                                        'is null' => 'Is Null',
                                        'is not null' => 'Is Not Null',
                                    ])
                                    ->required()
                                    ->default('='),

                                Forms\Components\TextInput::make('value')
                                    ->label(__('auth::auth.value'))
                                    ->placeholder('{user.branch_id}')
                                    ->helperText(__('auth::auth.value_placeholders')),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel(__('auth::auth.add_condition'))
                            ->helperText(__('auth::auth.domain_filter_help')),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('auth::auth.policy_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label(__('auth::auth.model_type'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('role.name')
                    ->label(__('auth::auth.role'))
                    ->placeholder(__('auth::auth.all_roles'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('auth::auth.priority'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('perm_read')
                    ->label('R')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash'),

                Tables\Columns\IconColumn::make('perm_create')
                    ->label('C')
                    ->boolean()
                    ->trueIcon('heroicon-o-plus-circle')
                    ->falseIcon('heroicon-o-minus-circle'),

                Tables\Columns\IconColumn::make('perm_update')
                    ->label('U')
                    ->boolean()
                    ->trueIcon('heroicon-o-pencil-square')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\IconColumn::make('perm_delete')
                    ->label('D')
                    ->boolean()
                    ->trueIcon('heroicon-o-trash')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('auth::auth.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('auth::auth.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority')
            ->filters([
                Tables\Filters\SelectFilter::make('role_id')
                    ->label(__('auth::auth.role'))
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('auth::auth.active')),

                Tables\Filters\TernaryFilter::make('apply_to_all_roles')
                    ->label(__('auth::auth.apply_to_all_roles')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label(__('auth::auth.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (AccessPolicy $record) {
                        $newPolicy = $record->replicate();
                        $newPolicy->name = $record->name . ' (Copy)';
                        $newPolicy->save();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessPolicies::route('/'),
            'create' => Pages\CreateAccessPolicy::route('/create'),
            'view' => Pages\ViewAccessPolicy::route('/{record}'),
            'edit' => Pages\EditAccessPolicy::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
