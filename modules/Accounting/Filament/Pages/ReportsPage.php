<?php

namespace Modules\Accounting\Filament\Pages;

use Filament\Pages\Page;

class ReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'reports';

    // This is a dummy view - we redirect immediately
    protected static string $view = 'accounting::filament.pages.reports-redirect';

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public function mount(): void
    {
        // Redirect to P&L as the default report
        $this->redirect(ProfitLossPage::getUrl());
    }
}
