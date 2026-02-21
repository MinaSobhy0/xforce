<?php

namespace Modules\Billing\Filament\Clusters;

use Filament\Clusters\Cluster;

class Reports extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('Reports');
    }
}
