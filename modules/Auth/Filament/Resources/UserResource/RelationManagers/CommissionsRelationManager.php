<?php

namespace Modules\Auth\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Staff\Models\StaffCommissionRecord;

class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionRecords';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('staff::staff.fields.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('appointment.code')
                    ->label(__('staff::staff.fields.appointment'))
                    ->url(fn ($record) => $record->appointment_id
                        ? route('filament.tenant.resources.appointments.view', $record->appointment_id)
                        : null),

                Tables\Columns\TextColumn::make('appointment.treatment.name')
                    ->label(__('staff::staff.fields.treatment'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state)
                    ->limit(25),

                Tables\Columns\TextColumn::make('source_amount_minor')
                    ->label(__('staff::staff.fields.revenue'))
                    ->money(current_currency(), divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('staff::staff.fields.commission'))
                    ->money(current_currency(), divideBy: 100)
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('staff::staff.fields.status'))
                    ->colors([
                        'warning' => StaffCommissionRecord::STATUS_PENDING,
                        'info' => StaffCommissionRecord::STATUS_APPROVED,
                        'success' => StaffCommissionRecord::STATUS_PAID,
                        'danger' => StaffCommissionRecord::STATUS_CANCELLED,
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        StaffCommissionRecord::STATUS_PENDING => __('staff::staff.statuses.pending'),
                        StaffCommissionRecord::STATUS_APPROVED => __('staff::staff.statuses.approved'),
                        StaffCommissionRecord::STATUS_PAID => __('staff::staff.statuses.paid'),
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('staff::staff.labels.commissions');
    }
}
