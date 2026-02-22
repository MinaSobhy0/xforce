<?php

namespace Modules\Auth\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\UserBranchRole;
use Modules\Core\Models\Branch;

class BranchRolesRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'branchRoles';

    protected static ?string $title = 'Branch Access';

    protected static ?string $icon = 'heroicon-o-building-office-2';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->label(__('auth::auth.fields.branch'))
                    ->options(function () {
                        return Branch::where('is_active', true)
                            ->orderBy('is_main', 'desc')
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),

                Forms\Components\Select::make('role_id')
                    ->label(__('auth::auth.fields.role'))
                    ->options(function () {
                        return Role::query()
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('auth::auth.fields.is_primary'))
                            ->helperText(__('auth::auth.helpers.primary_branch'))
                            ->default(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('auth::auth.fields.is_active'))
                            ->default(true),
                    ]),

                Forms\Components\DatePicker::make('expires_at')
                    ->label(__('auth::auth.fields.expires_at'))
                    ->helperText(__('auth::auth.helpers.expires_at'))
                    ->nullable()
                    ->minDate(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('branch.name')
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('auth::auth.fields.branch'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('role.name')
                    ->label(__('auth::auth.fields.role'))
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label(__('auth::auth.fields.is_primary'))
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('auth::auth.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('auth::auth.fields.expires_at'))
                    ->date()
                    ->placeholder(__('Never'))
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('assigned_at')
                    ->label(__('auth::auth.fields.assigned_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('auth::auth.fields.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('auth::auth.fields.is_active')),

                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label(__('auth::auth.fields.is_primary')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('auth::auth.actions.assign_branch'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['assigned_at'] = now();
                        $data['assigned_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function (UserBranchRole $record) {
                        // If this is set as primary, remove primary from others
                        if ($record->is_primary) {
                            UserBranchRole::where('user_id', $record->user_id)
                                ->where('id', '!=', $record->id)
                                ->update(['is_primary' => false]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (UserBranchRole $record) {
                        // If this is set as primary, remove primary from others
                        if ($record->is_primary) {
                            UserBranchRole::where('user_id', $record->user_id)
                                ->where('id', '!=', $record->id)
                                ->update(['is_primary' => false]);
                        }
                    }),

                Tables\Actions\Action::make('makePrimary')
                    ->label(__('auth::auth.actions.make_primary'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (UserBranchRole $record) => !$record->is_primary && $record->is_active)
                    ->requiresConfirmation()
                    ->action(function (UserBranchRole $record) {
                        $record->makePrimary();
                        Notification::make()
                            ->title(__('auth::auth.messages.primary_branch_set'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (UserBranchRole $record) => $record->is_active
                        ? __('auth::auth.actions.deactivate')
                        : __('auth::auth.actions.activate'))
                    ->icon(fn (UserBranchRole $record) => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (UserBranchRole $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (UserBranchRole $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        Notification::make()
                            ->title($record->is_active
                                ? __('auth::auth.messages.branch_access_activated')
                                : __('auth::auth.messages.branch_access_deactivated'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('is_primary', 'desc');
    }
}
