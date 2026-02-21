<?php

namespace App\Filament\SuperAdmin\Resources\SystemAlertResource\Pages;

use App\Filament\SuperAdmin\Resources\SystemAlertResource;
use App\Models\SystemAlert;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSystemAlerts extends BaseListRecords
{
    protected static string $resource = SystemAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\Action::make('resolve_all_critical')
                ->label('Resolve All Critical')
                ->icon('heroicon-o-check')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => SystemAlert::unresolved()->critical()->exists())
                ->action(function () {
                    SystemAlert::unresolved()->critical()->get()
                        ->each(fn($alert) => $alert->resolve(auth()->id()));
                    \Filament\Notifications\Notification::make()
                        ->title('All critical alerts resolved')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SystemAlertResource\Widgets\SystemHealthWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'critical' => Tab::make('Critical')
                ->badge(SystemAlert::unresolved()->critical()->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->unresolved()->critical()),

            'warning' => Tab::make('Warning')
                ->badge(SystemAlert::unresolved()->warning()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->unresolved()->warning()),

            'info' => Tab::make('Info')
                ->modifyQueryUsing(fn(Builder $query) => $query->unresolved()->info()),

            'resolved' => Tab::make('Resolved')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereRaw('is_resolved = true')),

            'all' => Tab::make('All'),
        ];
    }
}
