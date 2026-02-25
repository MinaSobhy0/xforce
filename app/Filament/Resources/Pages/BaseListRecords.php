<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Traits\HasExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class BaseListRecords extends ListRecords
{
    use HasExportAction;

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
}
