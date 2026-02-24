<?php

namespace Modules\Auth\Filament\Resources;

use XLinic\Framework\Core\Filament\BaseResource;
use Modules\Auth\Models\User;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\UserStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Modules\Auth\Filament\Resources\UserResource\Pages;
use Modules\Auth\Filament\Resources\UserResource\RelationManagers;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $moduleCode = 'auth';
    protected static ?string $permissionKey = 'users';

    public static function getNavigationLabel(): string
    {
        return __('auth::auth.labels.users');
    }

    public static function getModelLabel(): string
    {
        return __('auth::auth.labels.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auth::auth.labels.users');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make(__('auth::auth.user_resource.tabs.user_information'))
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('auth::auth.user_resource.tabs.basic_information'))
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('first_name')
                                            ->label(__('auth::auth.user_resource.first_name'))
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('last_name')
                                            ->label(__('auth::auth.user_resource.last_name'))
                                            ->required()
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('email')
                                            ->label(__('auth::auth.user_resource.email'))
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('username')
                                            ->label(__('auth::auth.user_resource.username'))
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('phone')
                                            ->label(__('auth::auth.user_resource.phone'))
                                            ->tel()
                                            ->nullable()
                                            ->maxLength(20),

                                        Forms\Components\DatePicker::make('date_of_birth')
                                            ->label(__('auth::auth.user_resource.date_of_birth'))
                                            ->nullable()
                                            ->maxDate(now()->subYears(16)),
                                    ]),

                                Forms\Components\Select::make('gender')
                                    ->label(__('auth::auth.user_resource.gender'))
                                    ->options([
                                        'male' => __('auth::auth.user_resource.male'),
                                        'female' => __('auth::auth.user_resource.female'),
                                        'other' => __('auth::auth.user_resource.other'),
                                    ])
                                    ->nullable(),

                                Forms\Components\Textarea::make('address')
                                    ->label(__('auth::auth.user_resource.address'))
                                    ->nullable()
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('auth::auth.user_resource.tabs.account_settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('password')
                                            ->label(__('auth::auth.user_resource.password'))
                                            ->password()
                                            ->revealable()
                                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->required(fn (string $context): bool => $context === 'create')
                                            ->confirmed()
                                            ->minLength(8)
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('password_confirmation')
                                            ->label(__('auth::auth.user_resource.confirm_password'))
                                            ->password()
                                            ->revealable()
                                            ->required(fn (string $context): bool => $context === 'create')
                                            ->dehydrated(false),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label(__('auth::auth.user_resource.status'))
                                            ->options(UserStatus::class)
                                            ->default(UserStatus::ACTIVE)
                                            ->required(),

                                        Forms\Components\Toggle::make('email_verified_at')
                                            ->label(__('auth::auth.user_resource.email_verified'))
                                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null),

                                        Forms\Components\Toggle::make('must_change_password')
                                            ->label(__('auth::auth.user_resource.must_change_password'))
                                            ->helperText(__('auth::auth.user_resource.must_change_password_help'))
                                            ->default(false),
                                    ]),

                                Forms\Components\Select::make('roles')
                                    ->label(__('auth::auth.user_resource.roles'))
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),

                                Forms\Components\Select::make('branch_ids')
                                    ->label(__('auth::auth.user_resource.allowed_branches'))
                                    ->options(function () {
                                        $allowedBranchIds = \App\Services\BranchContext::userAllowedIds();

                                        $query = \Modules\Core\Models\Branch::where('is_active', true)
                                            ->orderBy('is_main', 'desc')
                                            ->orderBy('name');

                                        // If user has restricted branches, only show those
                                        if (!empty($allowedBranchIds)) {
                                            $query->whereIn('id', $allowedBranchIds);
                                        }

                                        return $query->pluck('name', 'id');
                                    })
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText(__('auth::auth.user_resource.allowed_branches_help'))
                                    ->afterStateHydrated(function (Forms\Components\Select $component, ?User $record) {
                                        if ($record) {
                                            $branchIds = $record->branchRoles()
                                                ->where('is_active', true)
                                                ->pluck('branch_id')
                                                ->toArray();
                                            $component->state($branchIds);
                                        }
                                    })
                                    ->dehydrated(false),

                                Forms\Components\DateTimePicker::make('last_login_at')
                                    ->label(__('auth::auth.user_resource.last_login'))
                                    ->nullable()
                                    ->displayFormat('Y-m-d H:i:s')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('auth::auth.user_resource.tabs.profile'))
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Forms\Components\FileUpload::make('avatar_url')
                                    ->label(__('auth::auth.user_resource.profile_picture'))
                                    ->image()
                                    ->directory('users/avatars')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->circleCropper()
                                    ->nullable(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('job_title')
                                            ->label(__('auth::auth.user_resource.job_title'))
                                            ->nullable()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('department')
                                            ->label(__('auth::auth.user_resource.department'))
                                            ->nullable()
                                            ->maxLength(100),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('timezone')
                                            ->label(__('auth::auth.user_resource.timezone'))
                                            ->options([
                                                'Africa/Cairo' => 'Cairo (GMT+2)',
                                                'Asia/Riyadh' => 'Riyadh (GMT+3)',
                                                'Asia/Dubai' => 'Dubai (GMT+4)',
                                                'Asia/Kuwait' => 'Kuwait (GMT+3)',
                                                'Asia/Qatar' => 'Qatar (GMT+3)',
                                            ])
                                            ->default('Africa/Cairo')
                                            ->searchable(),

                                        Forms\Components\Select::make('locale')
                                            ->label(__('auth::auth.user_resource.language'))
                                            ->options([
                                                'ar' => __('auth::auth.user_resource.arabic'),
                                                'en' => __('auth::auth.user_resource.english'),
                                            ])
                                            ->default('en'),
                                    ]),

                                Forms\Components\Textarea::make('bio')
                                    ->label(__('auth::auth.user_resource.biography'))
                                    ->nullable()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('auth::auth.user_resource.tabs.two_factor'))
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Toggle::make('two_factor_enabled')
                                    ->label(__('auth::auth.user_resource.enable_2fa'))
                                    ->helperText(__('auth::auth.user_resource.enable_2fa_help'))
                                    ->default(false)
                                    ->live(),

                                Forms\Components\TextInput::make('two_factor_backup_codes')
                                    ->label(__('auth::auth.user_resource.backup_codes'))
                                    ->helperText(__('auth::auth.user_resource.backup_codes_help'))
                                    ->visible(fn (Forms\Get $get) => $get('two_factor_enabled'))
                                    ->nullable(),

                                Forms\Components\DateTimePicker::make('two_factor_confirmed_at')
                                    ->label(__('auth::auth.user_resource.2fa_confirmed_at'))
                                    ->visible(fn (Forms\Get $get) => $get('two_factor_enabled'))
                                    ->nullable()
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label(__('auth::auth.user_resource.avatar'))
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('auth::auth.user_resource.name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name'])
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('auth::auth.user_resource.email'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('auth::auth.user_resource.roles'))
                    ->badge()
                    ->separator(',')
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'primary' => 'manager',
                        'success' => 'user',
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('auth::auth.user_resource.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => match ($state) {
                        UserStatus::ACTIVE => 'success',
                        UserStatus::INACTIVE => 'gray',
                        UserStatus::SUSPENDED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label(__('auth::auth.user_resource.verified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark'),

                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->label(__('auth::auth.user_resource.2fa'))
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('auth::auth.user_resource.last_login'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('auth::auth.user_resource.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('auth::auth.user_resource.status'))
                    ->options(UserStatus::class),

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label(__('auth::auth.user_resource.email_verified'))
                    ->nullable(),

                Tables\Filters\SelectFilter::make('roles')
                    ->label(__('auth::auth.user_resource.role'))
                    ->relationship('roles', 'name')
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('two_factor_enabled')
                    ->label(__('auth::auth.user_resource.two_factor_auth'))
                    ->boolean(),

                Tables\Filters\Filter::make('inactive_users')
                    ->label(__('auth::auth.user_resource.inactive_users'))
                    ->query(fn (Builder $query): Builder => $query->where('last_login_at', '<', now()->subDays(30))),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('impersonate')
                    ->label(__('auth::auth.user_resource.login_as_user'))
                    ->icon('heroicon-o-user')
                    ->color('warning')
                    ->action(function (User $record) {
                        // Impersonation logic would go here
                        // This is a placeholder for the functionality
                    })
                    ->visible(fn (User $record) => $record->status === 'active' && !$record->hasRole('super_admin')),

                Tables\Actions\Action::make('resetPassword')
                    ->label(__('auth::auth.user_resource.reset_password'))
                    ->icon('heroicon-o-key')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label(__('auth::auth.user_resource.new_password'))
                            ->password()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'password' => Hash::make($data['new_password']),
                            'must_change_password' => true,
                            'password_changed_at' => now(),
                        ]);
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('activate')
                        ->label(__('auth::auth.user_resource.activate'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'active'])),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label(__('auth::auth.user_resource.deactivate'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['status' => 'inactive'])),

                    Tables\Actions\BulkAction::make('forcePasswordChange')
                        ->label(__('auth::auth.user_resource.force_password_change'))
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['must_change_password' => true])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('auth::auth.user_resource.user_profile'))
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label(__('auth::auth.user_resource.name'))
                                        ->weight(FontWeight::Bold),
                                    Infolists\Components\TextEntry::make('email')
                                        ->label(__('auth::auth.user_resource.email'))
                                        ->copyable(),
                                    Infolists\Components\TextEntry::make('phone')
                                        ->label(__('auth::auth.user_resource.phone'))
                                        ->copyable(),
                                    Infolists\Components\TextEntry::make('job_title')
                                        ->label(__('auth::auth.user_resource.job_title')),
                                ]),
                            Infolists\Components\ImageEntry::make('avatar_url')
                                ->hiddenLabel()
                                ->circular()
                                ->grow(false),
                        ])->from('lg'),
                    ]),

                Infolists\Components\Section::make(__('auth::auth.user_resource.account_status'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('auth::auth.user_resource.status'))
                                    ->badge()
                                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                                    ->color(fn (UserStatus $state): string => match ($state) {
                                        UserStatus::ACTIVE => 'success',
                                        UserStatus::INACTIVE => 'gray',
                                        UserStatus::SUSPENDED => 'danger',
                                        default => 'gray',
                                    }),
                                Infolists\Components\IconEntry::make('email_verified_at')
                                    ->label(__('auth::auth.user_resource.email_verified'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('two_factor_enabled')
                                    ->label(__('auth::auth.user_resource.2fa_enabled'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('must_change_password')
                                    ->label(__('auth::auth.user_resource.must_change_password'))
                                    ->boolean(),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('auth::auth.user_resource.role_permissions'))
                    ->schema([
                        Infolists\Components\TextEntry::make('roles.name')
                            ->label(__('auth::auth.user_resource.roles'))
                            ->badge()
                            ->separator(','),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivityLogRelationManager::class,
            RelationManagers\SessionsRelationManager::class,
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\CommissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    protected static ?string $slug = 'users';
}