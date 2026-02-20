<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Users';

    protected static ?string $recordTitleAttribute = 'email';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->required()
                    ->maxLength(100),

                Forms\Components\TextInput::make('last_name')
                    ->required()
                    ->maxLength(100),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('phone')
                    ->tel(),

                Forms\Components\Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        fn ($query) => $query->whereIn('name', [
                            'owner', 'admin', 'manager', 'doctor', 'nurse',
                            'technician', 'receptionist', 'staff'
                        ])
                    )
                    ->multiple()
                    ->preload()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\IconColumn::make('status')
                    ->label('Active')
                    ->icon(fn ($state) => $state?->value === 'active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn ($state) => $state?->value === 'active' ? 'success' : 'danger'),

                Tables\Columns\IconColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['password'] = Hash::make(Str::random(16));
                        $data['tenant_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will generate a new random password and send it to the user\'s email.')
                    ->action(function ($record): void {
                        $newPassword = Str::random(12);
                        $record->update([
                            'password' => Hash::make($newPassword),
                            'must_change_password' => true,
                        ]);

                        // TODO: Send password reset email with new password

                        Notification::make()
                            ->title('Password reset')
                            ->body("New password has been generated and will be sent to {$record->email}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('disable')
                    ->label('Disable')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status?->value === 'active')
                    ->action(function ($record): void {
                        $record->update(['status' => 'inactive']);

                        Notification::make()
                            ->title('User disabled')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('enable')
                    ->label('Enable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status?->value !== 'active')
                    ->action(function ($record): void {
                        $record->update(['status' => 'active']);

                        Notification::make()
                            ->title('User enabled')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
