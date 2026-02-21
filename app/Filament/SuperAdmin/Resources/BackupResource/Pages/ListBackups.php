<?php

namespace App\Filament\SuperAdmin\Resources\BackupResource\Pages;

use App\Filament\SuperAdmin\Resources\BackupResource;
use App\Models\Backup;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBackups extends BaseListRecords
{
    protected static string $resource = BackupResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            BackupResource\Widgets\BackupStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Backup::count()),

            'completed' => Tab::make('Completed')
                ->badge(Backup::where('status', 'completed')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'completed')),

            'running' => Tab::make('Running')
                ->badge(Backup::where('status', 'running')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'running')),

            'failed' => Tab::make('Failed')
                ->badge(Backup::where('status', 'failed')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'failed')),
        ];
    }
}
