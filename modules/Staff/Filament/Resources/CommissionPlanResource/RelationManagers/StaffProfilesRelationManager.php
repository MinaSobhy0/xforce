<?php

namespace Modules\Staff\Filament\Resources\CommissionPlanResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Staff\Models\StaffProfile;

class StaffProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'staffProfiles';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('staff::commission.labels.assigned_staff');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('staff::staff.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee_number')
                    ->label(__('staff::staff.fields.employee_number'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('job_title')
                    ->label(__('staff::staff.fields.job_title')),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('staff::staff.fields.branch'))
                    ->getStateUsing(fn (StaffProfile $record) => $record->branch?->name),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('staff::staff.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('filament::resources/pages/view-record.title'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (StaffProfile $record) => route('filament.admin.resources.staff-profiles.view', $record)),
            ])
            ->bulkActions([]);
    }
}
