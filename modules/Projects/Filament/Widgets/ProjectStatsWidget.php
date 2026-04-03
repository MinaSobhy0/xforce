<?php

namespace Modules\Projects\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Models\ProjectTimeEntry;

class ProjectStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeProjects = Project::active()->notTemplate()->byStatus(Project::STATUS_ACTIVE)->count();
        $totalTasks = ProjectTask::incomplete()->count();
        $myTasks = ProjectTask::incomplete()->assignedTo(auth()->id())->count();
        $overdueTasks = ProjectTask::overdue()->count();
        $hoursThisWeek = ProjectTimeEntry::forUser(auth()->id())->thisWeek()->sum('hours');

        return [
            Stat::make(__('projects::projects.stats.active_projects'), $activeProjects)
                ->description(__('projects::projects.stats.active_projects_desc'))
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make(__('projects::projects.stats.open_tasks'), $totalTasks)
                ->description(__('projects::projects.stats.open_tasks_desc'))
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('info'),

            Stat::make(__('projects::projects.stats.my_tasks'), $myTasks)
                ->description(__('projects::projects.stats.my_tasks_desc'))
                ->descriptionIcon('heroicon-m-user')
                ->color('warning'),

            Stat::make(__('projects::projects.stats.overdue_tasks'), $overdueTasks)
                ->description(__('projects::projects.stats.overdue_tasks_desc'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueTasks > 0 ? 'danger' : 'success'),

            Stat::make(__('projects::projects.stats.hours_this_week'), number_format($hoursThisWeek, 1) . ' h')
                ->description(__('projects::projects.stats.hours_this_week_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),
        ];
    }
}
