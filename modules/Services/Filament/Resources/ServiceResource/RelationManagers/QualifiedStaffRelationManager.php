<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\User;

class QualifiedStaffRelationManager extends RelationManager
{
    protected static string $relationship = 'qualifiedStaff';

    protected static ?string $title = 'Qualified Staff';

    protected static ?string $recordTitleAttribute = 'first_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label(__('services::services.staff.practitioner'))
                    ->options(
                        User::query()
                            ->active()
                            ->get()
                            ->pluck('full_name', 'id')
                    )
                    ->required()
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->select([
                'users.id',
                'users.tenant_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.job_title',
                'users.status',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label(__('services::services.staff.name'))
                    ->formatStateUsing(fn ($record) => $record->full_name)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('job_title')
                    ->label(__('services::services.staff.job_title'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('services::services.staff.email'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('services::services.staff.phone')),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->active()->select([
                        'users.id',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'users.status',
                    ]))
                    ->recordTitle(fn (User $record) => $record->full_name),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
