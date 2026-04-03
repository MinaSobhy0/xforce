<?php

namespace Modules\OdooIntegration\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Modules\OdooIntegration\Models\OdooConnection;
use Modules\OdooIntegration\Models\OdooSyncLog;
use Modules\OdooIntegration\Models\OdooSyncConflict;
use Modules\OdooIntegration\Jobs\BatchSyncJob;
use Modules\OdooIntegration\Filament\Widgets\SyncStatusWidget;
use Modules\OdooIntegration\Filament\Widgets\PendingConflictsWidget;

class OdooSyncDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'odoo-integration::filament.pages.sync-dashboard';

    public function getTitle(): string
    {
        return __('odoo-integration::odoo.pages.dashboard');
    }

    public static function getNavigationLabel(): string
    {
        return __('odoo-integration::odoo.pages.dashboard');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = OdooSyncConflict::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_all')
                ->label(__('odoo-integration::odoo.actions.sync_all'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('odoo-integration::odoo.modals.sync_all_title'))
                ->modalDescription(__('odoo-integration::odoo.modals.sync_all_description'))
                ->action(function () {
                    dispatch(new BatchSyncJob(
                        tenantId: current_tenant_id(),
                        syncType: 'delta',
                        triggeredBy: auth()->id(),
                    ));

                    Notification::make()
                        ->title(__('odoo-integration::odoo.messages.sync_queued'))
                        ->success()
                        ->send();
                }),

            Action::make('full_sync')
                ->label(__('odoo-integration::odoo.actions.full_sync'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('odoo-integration::odoo.modals.full_sync_title'))
                ->modalDescription(__('odoo-integration::odoo.modals.full_sync_description'))
                ->action(function () {
                    dispatch(new BatchSyncJob(
                        tenantId: current_tenant_id(),
                        syncType: 'full',
                        triggeredBy: auth()->id(),
                    ));

                    Notification::make()
                        ->title(__('odoo-integration::odoo.messages.sync_queued'))
                        ->body(__('odoo-integration::odoo.messages.full_sync_warning'))
                        ->warning()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SyncStatusWidget::class,
            PendingConflictsWidget::class,
        ];
    }

    public function getViewData(): array
    {
        return [
            'connections' => OdooConnection::where('is_active', true)->get(),
            'recentLogs' => OdooSyncLog::with(['connection', 'entityMapping'])
                ->orderBy('started_at', 'desc')
                ->limit(10)
                ->get(),
            'pendingConflicts' => OdooSyncConflict::where('status', 'pending')
                ->with(['entityMapping', 'syncRecord'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];
    }
}
