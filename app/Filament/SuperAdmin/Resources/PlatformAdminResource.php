<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformAdminResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PlatformAdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Platform Admins';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'platform-admins';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('tenant_id')
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['super_admin', 'platform_support_lead', 'platform_support', 'platform_billing', 'platform_readonly']);
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Admin Information')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->afterStateHydrated(function ($component, $record) {
                            if ($record) {
                                $component->state(trim($record->first_name . ' ' . $record->last_name));
                            }
                        }),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->required(fn($operation) => $operation === 'create')
                        ->dehydrated(fn($state) => filled($state))
                        ->dehydrateStateUsing(fn($state) => bcrypt($state))
                        ->maxLength(255)
                        ->helperText(fn($operation) => $operation === 'edit' ? 'Leave empty to keep current password' : ''),

                    Forms\Components\Select::make('roles')
                        ->label('Role')
                        ->relationship('roles', 'name', fn($query) => $query->whereIn('name', [
                            'super_admin',
                            'platform_support_lead',
                            'platform_support',
                            'platform_billing',
                            'platform_readonly',
                        ]))
                        ->preload()
                        ->required(),
                ]),

            Forms\Components\Section::make('Security')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('two_factor_enabled')
                        ->label('Two-Factor Authentication')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\Placeholder::make('last_login_at')
                        ->label('Last Login')
                        ->content(fn($record) => $record?->last_login_at?->diffForHumans() ?? 'Never'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(['first_name', 'last_name'])
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'super_admin' => 'Super Admin',
                        'platform_support_lead' => 'Support Lead',
                        'platform_support' => 'Support',
                        'platform_billing' => 'Billing',
                        'platform_readonly' => 'Read Only',
                        default => $state,
                    })
                    ->color(fn($state) => match ($state) {
                        'super_admin' => 'danger',
                        'platform_support_lead' => 'warning',
                        'platform_support' => 'info',
                        'platform_billing' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),

                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->label('2FA')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name', fn($query) => $query->whereIn('name', [
                        'super_admin',
                        'platform_support_lead',
                        'platform_support',
                        'platform_billing',
                        'platform_readonly',
                    ]))
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),

                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->password()
                            ->required()
                            ->label('Confirm Password'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['password' => bcrypt($data['new_password'])]);
                        \Filament\Notifications\Notification::make()
                            ->title('Password reset')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->id !== auth()->id()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformAdmins::route('/'),
            'create' => Pages\CreatePlatformAdmin::route('/create'),
            'edit' => Pages\EditPlatformAdmin::route('/{record}/edit'),
        ];
    }
}
