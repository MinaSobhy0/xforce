<?php

namespace Modules\Auth\Filament\Resources\AccessPolicyResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Auth\Filament\Resources\AccessPolicyResource;

class CreateAccessPolicy extends CreateRecord
{
    protected static string $resource = AccessPolicyResource::class;
}
