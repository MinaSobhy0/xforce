<?php

namespace Modules\Auth\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\UserSession;

class SessionsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('Session ID'))
                    ->limit(16)
                    ->copyable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('IP Address'))
                    ->copyable(),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label(__('Device'))
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->user_agent)
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        if (str_contains($state, 'Mobile') || str_contains($state, 'Android') || str_contains($state, 'iPhone')) {
                            return 'Mobile';
                        } elseif (str_contains($state, 'Windows')) {
                            return 'Windows';
                        } elseif (str_contains($state, 'Macintosh')) {
                            return 'macOS';
                        } elseif (str_contains($state, 'Linux')) {
                            return 'Linux';
                        }
                        return 'Desktop';
                    }),

                Tables\Columns\TextColumn::make('last_activity')
                    ->label(__('Last Activity'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('Expires'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),

                Tables\Filters\Filter::make('expired')
                    ->label(__('Expired'))
                    ->query(fn ($query) => $query->where('expires_at', '<=', now()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label(__('Revoke'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('This will immediately terminate this session.'))
                    ->action(function (UserSession $record) {
                        $record->terminate();
                    }),

                Tables\Actions\ViewAction::make()
                    ->form([
                        Forms\Components\TextInput::make('id')
                            ->label(__('Session ID'))
                            ->disabled(),

                        Forms\Components\TextInput::make('session_id')
                            ->label(__('Laravel Session ID'))
                            ->disabled(),

                        Forms\Components\TextInput::make('ip_address')
                            ->label(__('IP Address'))
                            ->disabled(),

                        Forms\Components\Textarea::make('user_agent')
                            ->label(__('User Agent'))
                            ->disabled()
                            ->rows(3),

                        Forms\Components\DateTimePicker::make('last_activity')
                            ->label(__('Last Activity'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('Expires At'))
                            ->disabled(),
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('revoke_selected')
                        ->label(__('Revoke Selected'))
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('This will immediately terminate the selected sessions.'))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->terminate();
                            }
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_activity', 'desc')
            ->emptyStateHeading(__('No sessions'))
            ->emptyStateDescription(__('This user has no tracked sessions.'));
    }
}
