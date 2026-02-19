<?php

namespace Modules\Auth\Resources;

use XLinic\Framework\Core\Filament\BaseResource;
use Modules\Auth\Models\User;
use Modules\Auth\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Modules\Auth\Resources\UserResource\Pages;
use Modules\Auth\Resources\UserResource\RelationManagers;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 10;
    protected static ?string $moduleCode = 'auth';

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('User Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('Full Name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('email')
                                            ->label(__('Email Address'))
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('phone')
                                            ->label(__('Phone Number'))
                                            ->tel()
                                            ->nullable()
                                            ->maxLength(20),

                                        Forms\Components\DatePicker::make('date_of_birth')
                                            ->label(__('Date of Birth'))
                                            ->nullable()
                                            ->maxDate(now()->subYears(16)),
                                    ]),

                                Forms\Components\Select::make('gender')
                                    ->label(__('Gender'))
                                    ->options([
                                        'male' => __('Male'),
                                        'female' => __('Female'),
                                        'other' => __('Other'),
                                    ])
                                    ->nullable(),

                                Forms\Components\Textarea::make('address')
                                    ->label(__('Address'))
                                    ->nullable()
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Account Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('password')
                                            ->label(__('Password'))
                                            ->password()
                                            ->revealable()
                                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->required(fn (string $context): bool => $context === 'create')
                                            ->confirmed()
                                            ->minLength(8)
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('password_confirmation')
                                            ->label(__('Confirm Password'))
                                            ->password()
                                            ->revealable()
                                            ->required(fn (string $context): bool => $context === 'create')
                                            ->dehydrated(false),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('Active'))
                                            ->default(true),

                                        Forms\Components\Toggle::make('email_verified_at')
                                            ->label(__('Email Verified'))
                                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null),

                                        Forms\Components\Toggle::make('must_change_password')
                                            ->label(__('Must Change Password'))
                                            ->helperText(__('Force user to change password on next login'))
                                            ->default(false),
                                    ]),

                                Forms\Components\Select::make('roles')
                                    ->label(__('Roles'))
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),

                                Forms\Components\DateTimePicker::make('last_login_at')
                                    ->label(__('Last Login'))
                                    ->nullable()
                                    ->displayFormat('Y-m-d H:i:s')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Forms\Components\Tabs\Tab::make('Profile')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Forms\Components\FileUpload::make('avatar_url')
                                    ->label(__('Profile Picture'))
                                    ->image()
                                    ->directory('users/avatars')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->circleCropper()
                                    ->nullable(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('job_title')
                                            ->label(__('Job Title'))
                                            ->nullable()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('department')
                                            ->label(__('Department'))
                                            ->nullable()
                                            ->maxLength(100),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('timezone')
                                            ->label(__('Timezone'))
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
                                            ->label(__('Language'))
                                            ->options([
                                                'ar' => __('Arabic'),
                                                'en' => __('English'),
                                            ])
                                            ->default('en'),
                                    ]),

                                Forms\Components\Textarea::make('bio')
                                    ->label(__('Biography'))
                                    ->nullable()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Two-Factor Authentication')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Toggle::make('two_factor_enabled')
                                    ->label(__('Enable Two-Factor Authentication'))
                                    ->helperText(__('Require two-factor authentication for this user'))
                                    ->default(false)
                                    ->live(),

                                Forms\Components\TextInput::make('two_factor_backup_codes')
                                    ->label(__('Backup Codes'))
                                    ->helperText(__('Comma-separated backup codes'))
                                    ->visible(fn (Forms\Get $get) => $get('two_factor_enabled'))
                                    ->nullable(),

                                Forms\Components\DateTimePicker::make('two_factor_confirmed_at')
                                    ->label(__('Two-Factor Confirmed At'))
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
                    ->label(__('Avatar'))
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->separator(',')
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'primary' => 'manager',
                        'success' => 'user',
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label(__('Verified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark'),

                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->label(__('2FA'))
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('Last Login'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

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

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label(__('Email Verified'))
                    ->nullable(),

                Tables\Filters\SelectFilter::make('roles')
                    ->label(__('Role'))
                    ->relationship('roles', 'name')
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('two_factor_enabled')
                    ->label(__('Two-Factor Auth'))
                    ->boolean(),

                Tables\Filters\Filter::make('inactive_users')
                    ->label(__('Inactive Users'))
                    ->query(fn (Builder $query): Builder => $query->where('last_login_at', '<', now()->subDays(30))),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('impersonate')
                    ->label(__('Login as User'))
                    ->icon('heroicon-o-user')
                    ->color('warning')
                    ->action(function (User $record) {
                        // Impersonation logic would go here
                        // This is a placeholder for the functionality
                    })
                    ->visible(fn (User $record) => $record->is_active && !$record->hasRole('super_admin')),

                Tables\Actions\Action::make('resetPassword')
                    ->label(__('Reset Password'))
                    ->icon('heroicon-o-key')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label(__('New Password'))
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
                        ->label(__('Activate'))
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label(__('Deactivate'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),

                    Tables\Actions\BulkAction::make('forcePasswordChange')
                        ->label(__('Force Password Change'))
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
                Infolists\Components\Section::make(__('User Profile'))
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label(__('Name'))
                                        ->weight(FontWeight::Bold),
                                    Infolists\Components\TextEntry::make('email')
                                        ->label(__('Email'))
                                        ->copyable(),
                                    Infolists\Components\TextEntry::make('phone')
                                        ->label(__('Phone'))
                                        ->copyable(),
                                    Infolists\Components\TextEntry::make('job_title')
                                        ->label(__('Job Title')),
                                ]),
                            Infolists\Components\ImageEntry::make('avatar_url')
                                ->hiddenLabel()
                                ->circular()
                                ->grow(false),
                        ])->from('lg'),
                    ]),

                Infolists\Components\Section::make(__('Account Status'))
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label(__('Active'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('email_verified_at')
                                    ->label(__('Email Verified'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('two_factor_enabled')
                                    ->label(__('2FA Enabled'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('must_change_password')
                                    ->label(__('Must Change Password'))
                                    ->boolean(),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('Role & Permissions'))
                    ->schema([
                        Infolists\Components\TextEntry::make('roles.name')
                            ->label(__('Roles'))
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