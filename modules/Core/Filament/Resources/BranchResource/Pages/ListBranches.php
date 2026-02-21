<?php

namespace Modules\Core\Filament\Resources\BranchResource\Pages;

use Modules\Core\Filament\Resources\BranchResource;
use Modules\Core\Models\Branch;
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

    /**
     * Get header widgets to show branch limit status.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            BranchResource\Widgets\BranchLimitWidget::class,
        ];
    }
}
