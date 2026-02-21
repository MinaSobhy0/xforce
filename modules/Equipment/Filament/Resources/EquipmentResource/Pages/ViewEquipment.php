<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Equipment\Filament\Resources\EquipmentResource;
use Modules\Equipment\Models\Equipment;

class ViewEquipment extends BaseViewRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('log_maintenance')
                ->label(__('equipment::equipment.log_maintenance'))
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('type')
                        ->label(__('equipment::equipment.maintenance_type'))
                        ->options(\Modules\Equipment\Models\EquipmentMaintenanceLog::TYPES)
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label(__('equipment::equipment.description'))
                        ->rows(2),
                    \Filament\Forms\Components\TextInput::make('performed_by')
                        ->label(__('equipment::equipment.performed_by')),
                    \Filament\Forms\Components\TextInput::make('cost_minor')
                        ->label(__('equipment::equipment.cost'))
                        ->numeric()
                        ->prefix('EGP'),
                    \Filament\Forms\Components\DatePicker::make('next_due_date')
                        ->label(__('equipment::equipment.next_due_date')),
                ])
                ->action(function (Equipment $record, array $data) {
                    $record->maintenanceLogs()->create([
                        'type' => $data['type'],
                        'description' => $data['description'],
                        'performed_by' => $data['performed_by'],
                        'cost_minor' => $data['cost_minor'],
                        'next_due_date' => $data['next_due_date'],
                        'performed_at' => now(),
                    ]);
                }),
        ];
    }
}
