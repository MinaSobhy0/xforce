<?php

namespace Modules\Auth\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\AccessPolicy;
use Modules\Auth\Models\Role;
use Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

class AccessPolicyResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = AccessPolicy::class;

    protected static ?string $moduleCode = 'auth';

    protected static ?string $permissionKey = 'roles';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.access');
    }

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('auth::auth.navigation.access_policies');
    }

    public static function getModelLabel(): string
    {
        return __('auth::auth.labels.access_policy');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auth::auth.labels.access_policies');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('auth::auth.sections.policy_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('auth::auth.fields.name'))
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('model_type')
                            ->label(__('auth::auth.fields.model_type'))
                            ->required()
                            ->options(static::getModelOptions())
                            ->searchable(),

                        Forms\Components\Select::make('role_id')
                            ->label(__('auth::auth.fields.role'))
                            ->options(Role::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Toggle::make('apply_to_all_roles')
                            ->label(__('auth::auth.fields.apply_to_all_roles'))
                            ->helperText(__('auth::auth.helpers.apply_to_all_roles'))
                            ->default(false),

                        Forms\Components\TextInput::make('priority')
                            ->label(__('auth::auth.fields.priority'))
                            ->numeric()
                            ->default(10)
                            ->helperText(__('auth::auth.helpers.priority')),

                        Forms\Components\Textarea::make('description')
                            ->label(__('auth::auth.fields.description'))
                            ->rows(2),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('auth::auth.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('auth::auth.sections.permissions'))
                    ->schema([
                        Forms\Components\Toggle::make('perm_read')
                            ->label(__('auth::auth.fields.perm_read'))
                            ->default(true),

                        Forms\Components\Toggle::make('perm_create')
                            ->label(__('auth::auth.fields.perm_create'))
                            ->default(true),

                        Forms\Components\Toggle::make('perm_update')
                            ->label(__('auth::auth.fields.perm_update'))
                            ->default(true),

                        Forms\Components\Toggle::make('perm_delete')
                            ->label(__('auth::auth.fields.perm_delete'))
                            ->default(false),
                    ])
                    ->columns(4),

                Forms\Components\Section::make(__('auth::auth.sections.domain_filter'))
                    ->schema([
                        Forms\Components\Repeater::make('domain_filter')
                            ->label(__('auth::auth.fields.conditions'))
                            ->schema([
                                Forms\Components\TextInput::make('field')
                                    ->label(__('auth::auth.fields.field'))
                                    ->required()
                                    ->placeholder('branch_id'),

                                Forms\Components\Select::make('operator')
                                    ->label(__('auth::auth.fields.operator'))
                                    ->required()
                                    ->options([
                                        '=' => '=',
                                        '!=' => '!=',
                                        '>' => '>',
                                        '>=' => '>=',
                                        '<' => '<',
                                        '<=' => '<=',
                                        'in' => 'IN',
                                        'not in' => 'NOT IN',
                                        'like' => 'LIKE',
                                        'is null' => 'IS NULL',
                                        'is not null' => 'IS NOT NULL',
                                    ])
                                    ->default('='),

                                Forms\Components\TextInput::make('value')
                                    ->label(__('auth::auth.fields.value'))
                                    ->placeholder('{user.branch_id}')
                                    ->helperText(__('auth::auth.helpers.placeholders')),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel(__('auth::auth.actions.add_condition')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('auth::auth.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label(__('auth::auth.fields.model_type'))
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('role.name')
                    ->label(__('auth::auth.fields.role'))
                    ->default(__('auth::auth.all_roles'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('auth::auth.fields.priority'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('perm_read')
                    ->label(__('auth::auth.fields.read'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('perm_create')
                    ->label(__('auth::auth.fields.create'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('perm_update')
                    ->label(__('auth::auth.fields.update'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('perm_delete')
                    ->label(__('auth::auth.fields.delete'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('auth::auth.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role_id')
                    ->label(__('auth::auth.fields.role'))
                    ->options(Role::pluck('name', 'id')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('auth::auth.fields.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessPolicies::route('/'),
            'create' => Pages\CreateAccessPolicy::route('/create'),
            'edit' => Pages\EditAccessPolicy::route('/{record}/edit'),
        ];
    }

    protected static function getModelOptions(): array
    {
        return [
            \Modules\Patients\Models\Patient::class => 'Patient',
            \Modules\Booking\Models\Appointment::class => 'Appointment',
            \Modules\Billing\Models\Invoice::class => 'Invoice',
            \Modules\Staff\Models\StaffProfile::class => 'Staff Profile',
            \Modules\Equipment\Models\Equipment::class => 'Equipment',
            \Modules\Inventory\Models\Product::class => 'Product',
            \Modules\Services\Models\Service::class => 'Service',
        ];
    }
}
