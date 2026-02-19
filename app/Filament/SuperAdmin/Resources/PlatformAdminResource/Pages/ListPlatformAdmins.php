<?php

namespace App\Filament\SuperAdmin\Resources\PlatformAdminResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAdmins extends ListRecords
{
    protected static string $resource = PlatformAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Admin'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PlatformAdminResource\Widgets\PlatformRolesInfoWidget::class,
        ];
    }
}
