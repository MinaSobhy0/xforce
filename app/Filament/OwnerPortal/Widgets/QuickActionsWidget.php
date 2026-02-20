<?php

namespace App\Filament\OwnerPortal\Widgets;

use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.owner-portal.widgets.quick-actions';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;
}
