<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\OwnerUserResource\Pages;
use App\Models\OwnerUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use Modules\Core\Models\Tenant;

class OwnerUserResource extends Resource
{
    protected static ?string $model = OwnerUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Owner Portal Users';

    protected static ?string $modelLabel = 'Owner User';

    protected static ?string $pluralModelLabel = 'Owner Users';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('first_name')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(100),
                            ]),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('tenant_id')
                            ->label('Clinic (Tenant)')
                            ->options(fn () => Tenant::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The clinic this user owns/manages'),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->confirmed()
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep current password' : 'Minimum 8 characters'),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->requiredWith('password')
                            ->visible(fn (string $operation): bool => $operation === 'create' || true)
                            ->dehydrated(false),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Clinic')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'suspended',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Clinic')
                    ->options(fn () => Tenant::pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('setPassword')
                    ->label('Set Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),

                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->required(),
                    ])
                    ->action(function (OwnerUser $record, array $data): void {
                        $record->update([
                            'password' => Hash::make($data['new_password']),
                        ]);

                        Notification::make()
                            ->title('Password updated')
                            ->body("Password has been set for {$record->email}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('generatePassword')
                    ->label('Generate & Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('This will generate a random password and send it to the user\'s email.')
                    ->action(function (OwnerUser $record): void {
                        $newPassword = Str::random(12);

                        $record->update([
                            'password' => Hash::make($newPassword),
                        ]);

                        // Send email with password
                        try {
                            \Illuminate\Support\Facades\Mail::to($record->email)->send(
                                new \App\Mail\OwnerPasswordMail($record, $newPassword)
                            );

                            Notification::make()
                                ->title('Password sent')
                                ->body("New password has been sent to {$record->email}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Password updated but email failed')
                                ->body("Password: {$newPassword} (copy it now, email failed to send)")
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListOwnerUsers::route('/'),
            'create' => Pages\CreateOwnerUser::route('/create'),
            'edit' => Pages\EditOwnerUser::route('/{record}/edit'),
        ];
    }
}
