<?php

namespace Modules\Auth\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\Role;
use Modules\Auth\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Role::class;

    protected static ?string $moduleCode = 'auth';

    protected static ?string $permissionKey = 'roles';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('auth::auth.navigation.roles');
    }

    public static function getModelLabel(): string
    {
        return __('auth::auth.labels.role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auth::auth.labels.roles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('auth::auth.sections.role_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('auth::auth.fields.name'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record?->is_system),

                        Forms\Components\TextInput::make('display_name')
                            ->label(__('auth::auth.fields.display_name'))
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->label(__('auth::auth.fields.description'))
                            ->rows(2),

                        Forms\Components\TextInput::make('level')
                            ->label(__('auth::auth.fields.level'))
                            ->numeric()
                            ->default(10)
                            ->helperText(__('auth::auth.helpers.role_level')),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('auth::auth.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('auth::auth.sections.permissions'))
                    ->description(__('auth::auth.sections.permissions_description'))
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('grant_all')
                                ->label(__('auth::auth.actions.grant_all'))
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(function (Forms\Set $set) {
                                    foreach (static::getResourcePermissions() as $resource => $label) {
                                        foreach (['view', 'create', 'edit', 'delete'] as $action) {
                                            $set("permissions.{$resource}.{$action}", true);
                                        }
                                    }
                                }),
                            Forms\Components\Actions\Action::make('revoke_all')
                                ->label(__('auth::auth.actions.revoke_all'))
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->action(function (Forms\Set $set) {
                                    foreach (static::getResourcePermissions() as $resource => $label) {
                                        foreach (['view', 'create', 'edit', 'delete'] as $action) {
                                            $set("permissions.{$resource}.{$action}", false);
                                        }
                                    }
                                }),
                        ])->columnSpanFull(),

                        ...static::getPermissionSchema(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected static function getPermissionSchema(): array
    {
        $resources = static::getResourcePermissions();
        $schema = [];

        foreach ($resources as $resource => $label) {
            $schema[] = Forms\Components\Fieldset::make($label)
                ->schema([
                    Forms\Components\Checkbox::make("permissions.{$resource}.view")
                        ->label(__('auth::auth.permissions.view'))
                        ->inline(),

                    Forms\Components\Checkbox::make("permissions.{$resource}.create")
                        ->label(__('auth::auth.permissions.create'))
                        ->inline(),

                    Forms\Components\Checkbox::make("permissions.{$resource}.edit")
                        ->label(__('auth::auth.permissions.edit'))
                        ->inline(),

                    Forms\Components\Checkbox::make("permissions.{$resource}.delete")
                        ->label(__('auth::auth.permissions.delete'))
                        ->inline(),
                ])
                ->columns(4);
        }

        return $schema;
    }

    public static function getResourcePermissions(): array
    {
        return [
            'patients' => __('auth::auth.resources.patients'),
            'appointments' => __('auth::auth.resources.appointments'),
            'invoices' => __('auth::auth.resources.invoices'),
            'payments' => __('auth::auth.resources.payments'),
            'services' => __('auth::auth.resources.services'),
            'products' => __('auth::auth.resources.products'),
            'equipment' => __('auth::auth.resources.equipment'),
            'staff' => __('auth::auth.resources.staff'),
            'payroll' => __('auth::auth.resources.payroll'),
            'reports' => __('auth::auth.resources.reports'),
            'campaigns' => __('auth::auth.resources.campaigns'),
            'packages' => __('auth::auth.resources.packages'),
            'memberships' => __('auth::auth.resources.memberships'),
            'gift_cards' => __('auth::auth.resources.gift_cards'),
            'users' => __('auth::auth.resources.users'),
            'roles' => __('auth::auth.resources.roles'),
            'branches' => __('auth::auth.resources.branches'),
            'settings' => __('auth::auth.resources.settings'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('auth::auth.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('auth::auth.fields.display_name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('auth::auth.fields.users_count'))
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('auth::auth.fields.permissions_count'))
                    ->counts('permissions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('level')
                    ->label(__('auth::auth.fields.level'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('auth::auth.fields.system'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('auth::auth.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('auth::auth.fields.active')),

                Tables\Filters\TernaryFilter::make('is_system')
                    ->label(__('auth::auth.fields.system')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn ($record) => $record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('level');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        $role = Role::find($data['id']);

        if ($role) {
            $permissions = $role->permissions->pluck('name')->toArray();

            foreach (static::getResourcePermissions() as $resource => $label) {
                foreach (['view', 'create', 'edit', 'delete'] as $action) {
                    $permName = "{$resource}.{$action}";
                    $data['permissions'][$resource][$action] = in_array($permName, $permissions);
                }
            }
        }

        return $data;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['permissions']);
        $data['guard_name'] = 'web';
        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['permissions']);
        return $data;
    }
}
