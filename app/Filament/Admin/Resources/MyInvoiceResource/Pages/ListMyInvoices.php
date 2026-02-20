<?php

namespace App\Filament\Admin\Resources\MyInvoiceResource\Pages;

use App\Filament\Admin\Resources\MyInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListMyInvoices extends ListRecords
{
    protected static string $resource = MyInvoiceResource::class;
}
