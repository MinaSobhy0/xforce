<?php

namespace Modules\Projects\Filament\Pages;

use Filament\Pages\Page;
use Modules\Projects\Filament\Widgets\ProjectStatsWidget;
use Modules\Projects\Filament\Widgets\MyTasksWidget;
use Modules\Projects\Filament\Widgets\ActiveTimerWidget;

class ProjectDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Projects';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'projects::filament.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('projects::projects.dashboard');
    }

    public function getTitle(): string
    {
        return __('projects::projects.dashboard');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ActiveTimerWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProjectStatsWidget::class,
            MyTasksWidget::class,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('projects.view_any') ?? false;
    }
}
