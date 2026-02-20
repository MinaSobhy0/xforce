<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.quick-actions';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;
}
