<?php

namespace App\Filament\SuperAdmin\Resources\PlatformAdminResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformAdminResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListPlatformAdmins extends BaseListRecords
{
    protected static string $resource = PlatformAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
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
