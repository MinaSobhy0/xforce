<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\User;

class QualifiedStaffRelationManager extends BaseRelationManager
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
                    ->label(__('services::services.staff.add_staff'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('services::services.staff.add_staff'))
                    ->recordSelect(fn () => Forms\Components\Select::make('recordId')
                        ->label(__('services::services.staff.practitioner'))
                        ->options(function () {
                            $attachedIds = $this->ownerRecord->qualifiedStaff()->pluck('users.id')->toArray();
                            return User::query()
                                ->active()
                                ->whereNotIn('id', $attachedIds)
                                ->get()
                                ->pluck('full_name', 'id');
                        })
                        ->searchable()
                        ->required()
                    ),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label(__('services::services.actions.remove')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label(__('services::services.actions.remove_selected')),
                ]),
            ]);
    }
}
