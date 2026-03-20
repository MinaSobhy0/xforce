<?php

namespace App\Filament\SuperAdmin\Resources\TenantAppCodeResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantAppCodeResource;
use App\Models\TenantAppCode;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantAppCode extends EditRecord
{
    protected static string $resource = TenantAppCodeResource::class;

    /**
     * Resolve the record using the central connection.
     */
    public function mount(int|string $record): void
    {
        $this->record = TenantAppCode::find($record);

        if (!$this->record) {
            abort(404);
        }

        $this->fillForm();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
