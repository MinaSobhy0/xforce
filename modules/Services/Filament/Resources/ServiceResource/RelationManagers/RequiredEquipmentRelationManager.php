<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Equipment\Models\Equipment;

class RequiredEquipmentRelationManager extends RelationManager
{
    protected static string $relationship = 'requiredEquipment';

    protected static ?string $title = 'Required Equipment';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('equipment_id')
                    ->label(__('services::services.equipment.equipment'))
                    ->options(
                        Equipment::query()
                            ->where('status', Equipment::STATUS_ACTIVE)
                            ->get()
                            ->mapWithKeys(fn ($eq) => [$eq->id => "{$eq->name} ({$eq->code})"])
                    )
                    ->required()
                    ->searchable(),

                Forms\Components\Toggle::make('is_mandatory')
                    ->label(__('services::services.equipment.is_mandatory'))
                    ->helperText(__('services::services.equipment.is_mandatory_help'))
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('services::services.equipment.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('services::services.equipment.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type.translated_name')
                    ->label(__('services::services.equipment.type'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('services::services.equipment.branch'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('pivot.is_mandatory')
                    ->label(__('services::services.equipment.is_mandatory'))
                    ->boolean(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('services::services.equipment.status'))
                    ->colors([
                        'success' => Equipment::STATUS_ACTIVE,
                        'warning' => Equipment::STATUS_MAINTENANCE,
                        'danger' => Equipment::STATUS_OUT_OF_SERVICE,
                        'gray' => Equipment::STATUS_RETIRED,
                    ]),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_mandatory')
                    ->label(__('services::services.equipment.is_mandatory'))
                    ->queries(
                        true: fn ($query) => $query->wherePivot('is_mandatory', true),
                        false: fn ($query) => $query->wherePivot('is_mandatory', false),
                    ),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('status', Equipment::STATUS_ACTIVE))
                    ->recordTitle(fn (Equipment $record) => "{$record->name} ({$record->code})")
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('is_mandatory')
                            ->label(__('services::services.equipment.is_mandatory'))
                            ->default(true),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('toggleMandatory')
                    ->label(fn ($record) => $record->pivot->is_mandatory
                        ? __('services::services.equipment.mark_optional')
                        : __('services::services.equipment.mark_mandatory'))
                    ->icon(fn ($record) => $record->pivot->is_mandatory
                        ? 'heroicon-o-minus-circle'
                        : 'heroicon-o-plus-circle')
                    ->color(fn ($record) => $record->pivot->is_mandatory ? 'gray' : 'success')
                    ->action(function ($record) {
                        $this->ownerRecord->requiredEquipment()->updateExistingPivot(
                            $record->id,
                            ['is_mandatory' => !$record->pivot->is_mandatory]
                        );
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
