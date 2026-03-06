<?php

namespace Modules\Services\Filament\Resources\ServiceCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Staff\Models\StaffProfile;

class CategoryStaffRelationManager extends RelationManager
{
    protected static string $relationship = 'qualifiedStaff';

    protected static ?string $title = 'Qualified Staff';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('staff_profile_id')
                    ->label(__('services::services.staff.practitioner'))
                    ->options(function () {
                        return StaffProfile::query()
                            ->with('user')
                            ->get()
                            ->mapWithKeys(fn ($profile) => [
                                $profile->id => $profile->user?->full_name ?? "Staff #{$profile->id}"
                            ]);
                    })
                    ->required()
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('services::services.staff.name'))
                    ->searchable(['user.first_name', 'user.last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('job_title')
                    ->label(__('services::services.staff.job_title'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '-') : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('services::services.staff.email'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.phone')
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
                            $attachedIds = $this->ownerRecord->qualifiedStaff()->pluck('staff_profiles.id')->toArray();
                            return StaffProfile::query()
                                ->with('user')
                                ->whereNotIn('id', $attachedIds)
                                ->get()
                                ->mapWithKeys(fn ($profile) => [
                                    $profile->id => $profile->user?->full_name ?? "Staff #{$profile->id}"
                                ]);
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
