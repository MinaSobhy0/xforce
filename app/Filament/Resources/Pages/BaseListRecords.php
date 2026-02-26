<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Actions\ExportTableAction;
use App\Filament\Traits\HasImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class BaseListRecords extends ListRecords
{
    use HasImportAction;

    /**
     * Configure the table with persistent filters, sorting, and search.
     */
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession();
    }

    /**
     * Get header actions including export and import.
     */
    protected function getHeaderActions(): array
    {
        $actions = [];

        // Add import action if available
        $importAction = $this->getImportHeaderAction();
        if ($importAction) {
            $actions[] = $importAction;
        }

        // Add export action
        $actions[] = ExportTableAction::make();

        return $actions;
    }
}
