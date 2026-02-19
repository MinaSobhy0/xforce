<?php

namespace Modules\Auth\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // This would need to be adjusted based on your session storage implementation
                DB::table('sessions')
                    ->where('user_id', $this->getOwnerRecord()->id)
                    ->orderBy('last_activity', 'desc')
            )
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
                        // Parse user agent to show device type
                        if (str_contains($state, 'Mobile') || str_contains($state, 'Android') || str_contains($state, 'iPhone')) {
                            return '📱 Mobile';
                        } elseif (str_contains($state, 'Windows')) {
                            return '🖥️ Windows';
                        } elseif (str_contains($state, 'Macintosh')) {
                            return '🍎 macOS';
                        } elseif (str_contains($state, 'Linux')) {
                            return '🐧 Linux';
                        }
                        return '💻 Desktop';
                    }),

                Tables\Columns\TextColumn::make('last_activity')
                    ->label(__('Last Activity'))
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::createFromTimestamp($state)->diffForHumans())
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_current')
                    ->label(__('Current'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->id === session()->getId())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_sessions')
                    ->label(__('Active Sessions'))
                    ->query(fn ($query) => $query->where('last_activity', '>', now()->subHours(2)->timestamp)),

                Tables\Filters\Filter::make('mobile_sessions')
                    ->label(__('Mobile Sessions'))
                    ->query(fn ($query) => $query->where('user_agent', 'like', '%Mobile%')
                        ->orWhere('user_agent', 'like', '%Android%')
                        ->orWhere('user_agent', 'like', '%iPhone%')),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label(__('Revoke'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('This will immediately terminate this session.'))
                    ->action(function ($record) {
                        // Revoke the session
                        DB::table('sessions')->where('id', $record->id)->delete();

                        $this->notify('success', __('Session revoked successfully'));
                    })
                    ->visible(fn ($record) => $record->id !== session()->getId()),

                Tables\Actions\ViewAction::make()
                    ->form([
                        Forms\Components\TextInput::make('id')
                            ->label(__('Session ID'))
                            ->disabled(),

                        Forms\Components\TextInput::make('ip_address')
                            ->label(__('IP Address'))
                            ->disabled(),

                        Forms\Components\Textarea::make('user_agent')
                            ->label(__('User Agent'))
                            ->disabled()
                            ->rows(3),

                        Forms\Components\TextInput::make('last_activity')
                            ->label(__('Last Activity'))
                            ->formatStateUsing(fn ($state) => \Carbon\Carbon::createFromTimestamp($state)->format('Y-m-d H:i:s'))
                            ->disabled(),

                        Forms\Components\Textarea::make('payload')
                            ->label(__('Session Data'))
                            ->formatStateUsing(function ($state) {
                                try {
                                    $decoded = base64_decode($state);
                                    $unserialized = unserialize($decoded);
                                    return json_encode($unserialized, JSON_PRETTY_PRINT);
                                } catch (\Exception $e) {
                                    return 'Unable to decode session data';
                                }
                            })
                            ->disabled()
                            ->rows(10),
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('revoke_selected')
                    ->label(__('Revoke Selected'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('This will immediately terminate the selected sessions.'))
                    ->action(function ($records) {
                        $sessionIds = collect($records)
                            ->pluck('id')
                            ->filter(fn ($id) => $id !== session()->getId())
                            ->values();

                        DB::table('sessions')->whereIn('id', $sessionIds)->delete();

                        $this->notify('success', __('Selected sessions revoked successfully'));
                    }),
            ])
            ->defaultSort('last_activity', 'desc')
            ->emptyStateHeading(__('No active sessions'))
            ->emptyStateDescription(__('This user has no active sessions.'));
    }
}