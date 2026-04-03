<?php

namespace Modules\Projects\Filament\Resources\ProjectResource\RelationManagers;

use Modules\Projects\Models\ProjectMember;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label(__('projects::projects.fields.user'))
                    ->options(User::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('role')
                    ->label(__('projects::projects.fields.role'))
                    ->options(ProjectMember::ROLES)
                    ->default(ProjectMember::ROLE_MEMBER)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('projects::projects.fields.user'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('projects::projects.fields.email'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('pivot.role')
                    ->label(__('projects::projects.fields.role'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ProjectMember::ROLE_MANAGER => 'primary',
                        ProjectMember::ROLE_MEMBER => 'success',
                        ProjectMember::ROLE_VIEWER => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ProjectMember::ROLES[$state] ?? $state),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('role')
                            ->label(__('projects::projects.fields.role'))
                            ->options(ProjectMember::ROLES)
                            ->default(ProjectMember::ROLE_MEMBER)
                            ->required(),
                    ])
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\Action::make('change_role')
                    ->label(__('projects::projects.actions.change_role'))
                    ->icon('heroicon-o-user-group')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label(__('projects::projects.fields.role'))
                            ->options(ProjectMember::ROLES)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $this->getOwnerRecord()->members()->updateExistingPivot($record->id, [
                            'role' => $data['role'],
                        ]);
                    }),

                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
