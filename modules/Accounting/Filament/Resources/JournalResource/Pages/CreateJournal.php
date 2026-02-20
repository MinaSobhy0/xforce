<?php

namespace Modules\Accounting\Filament\Resources\JournalResource\Pages;

use Modules\Accounting\Filament\Resources\JournalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJournal extends CreateRecord
{
    protected static string $resource = JournalResource::class;
}
