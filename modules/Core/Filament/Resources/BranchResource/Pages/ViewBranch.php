<?php

namespace Modules\Core\Filament\Resources\BranchResource\Pages;

use Modules\Core\Filament\Resources\BranchResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewBranch extends BaseViewRecord
{
    protected static string $resource = BranchResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
