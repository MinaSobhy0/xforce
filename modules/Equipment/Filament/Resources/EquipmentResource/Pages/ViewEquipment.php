<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Equipment\Filament\Resources\EquipmentResource;
use Modules\Equipment\Models\Equipment;
use Modules\Equipment\Models\EquipmentParameterTemplate;

class ViewEquipment extends BaseViewRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('apply_template')
                ->label(__('equipment::equipment.template.apply'))
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->visible(fn (Equipment $record) => $record->tracking_enabled && $record->parameter_template_id && $this->canEditEquipment())
                ->requiresConfirmation()
                ->modalDescription(__('equipment::equipment.template.apply_confirm'))
                ->action(function (Equipment $record) {
                    if ($record->parameterTemplate) {
                        $record->parameterTemplate->applyToEquipment($record);
                        Notification::make()
                            ->success()
                            ->title(__('equipment::equipment.template.applied'))
                            ->send();
                    }
                }),
            Actions\Action::make('log_maintenance')
                ->label(__('equipment::equipment.log_maintenance'))
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->visible(fn () => $this->canEditEquipment())
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
                        ->prefix(current_currency()),
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

    /**
     * Check if user can edit equipment (has equipment.edit permission)
     */
    protected function canEditEquipment(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // Super admin and key roles always have access
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        return $user->can('equipment.edit');
    }
}
