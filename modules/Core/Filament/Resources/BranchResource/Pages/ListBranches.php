<?php

namespace Modules\Core\Filament\Resources\BranchResource\Pages;

use Modules\Core\Filament\Resources\BranchResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListBranches extends BaseListRecords
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
