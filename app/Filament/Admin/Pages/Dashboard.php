<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Admin\Widgets\SubscriptionStatusWidget;
use App\Filament\Admin\Widgets\QuickActionsWidget;
use App\Filament\Admin\Widgets\RecentInvoicesWidget;
use App\Filament\Admin\Widgets\SupportTicketsWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = -2;

    public function getWidgets(): array
    {
        return [
            SubscriptionStatusWidget::class,
            QuickActionsWidget::class,
            RecentInvoicesWidget::class,
            SupportTicketsWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
