<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;
use Modules\OdooIntegration\Models\OdooConnection;

class CreateOdooConnection extends CreateRecord
{
    protected static string $resource = OdooConnectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = current_tenant_id();

        return $data;
    }

    /**
     * Add "Test Connection" next to Save/Cancel so admins can verify their
     * credentials before persisting a bad row. Builds a transient
     * OdooConnection from the current form state — nothing is saved.
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCreateAnotherFormAction(),
            Actions\Action::make('test_connection')
                ->label(__('odoo-integration::odoo.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function () {
                    $data = $this->form->getState();
                    $transient = new OdooConnection($data);
                    OdooConnectionResource::runTestConnection($transient);
                }),
            $this->getCancelFormAction(),
        ];
    }
}
