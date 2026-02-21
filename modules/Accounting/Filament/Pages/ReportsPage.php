<?php

namespace Modules\Accounting\Filament\Pages;

use Filament\Pages\Page;

class ReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'finance-reports';

    protected static string $view = 'accounting::filament.pages.reports-redirect';

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
