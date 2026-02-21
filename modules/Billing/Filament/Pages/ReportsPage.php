<?php

namespace Modules\Billing\Filament\Pages;

use Filament\Pages\Page;

class ReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'billing::filament.pages.reports';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'finance/reports';

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function mount(): \Illuminate\Http\RedirectResponse
    {
        return redirect('/admin/profit-loss');
    }
}
