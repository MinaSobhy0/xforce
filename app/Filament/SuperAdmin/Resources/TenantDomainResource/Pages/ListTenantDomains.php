<?php

namespace App\Filament\SuperAdmin\Resources\TenantDomainResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantDomainResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantDomains extends ListRecords
{
    protected static string $resource = TenantDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verify_all_dns')
                ->label('Verify All DNS')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $domains = \App\Models\TenantDomain::whereRaw('is_verified = false')->get();
                    $verified = 0;
                    foreach ($domains as $domain) {
                        if ($domain->verifyDns()) {
                            $verified++;
                        }
                    }
                    \Filament\Notifications\Notification::make()
                        ->title("Verified {$verified} of {$domains->count()} domains")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('renew_all_ssl')
                ->label('Renew All SSL')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation(),

            Actions\CreateAction::make()
                ->label('Add Domain'),
        ];
    }
}
