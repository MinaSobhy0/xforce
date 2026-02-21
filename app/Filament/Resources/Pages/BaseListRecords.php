<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Traits\HasExportAction;
use Filament\Resources\Pages\ListRecords;

class BaseListRecords extends ListRecords
{
    use HasExportAction;
}
