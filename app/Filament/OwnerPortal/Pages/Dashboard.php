<?php

namespace App\Filament\OwnerPortal\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\OwnerPortal\Widgets\SubscriptionStatusWidget;
use App\Filament\OwnerPortal\Widgets\QuickActionsWidget;
use App\Filament\OwnerPortal\Widgets\RecentInvoicesWidget;
use App\Filament\OwnerPortal\Widgets\SupportTicketsWidget;

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
