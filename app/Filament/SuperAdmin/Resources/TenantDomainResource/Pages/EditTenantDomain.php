<?php

namespace App\Filament\SuperAdmin\Resources\TenantDomainResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantDomainResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditTenantDomain extends BaseEditRecord
{
    protected static string $resource = TenantDomainResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\Action::make('verify_dns')
                ->label('Verify DNS')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn() => !$this->record->is_verified)
                ->action(function () {
                    if ($this->record->verifyDns()) {
                        \Filament\Notifications\Notification::make()
                            ->title('DNS Verified')
                            ->success()
                            ->send();
                        $this->refreshFormData(['is_verified', 'dns_verified_at']);
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('DNS verification failed')
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
