<?php

namespace App\Filament\Admin\Widgets;

use App\Models\RestoreRequest;
use Filament\Widgets\Widget;

class RestoreRequestsWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.restore-requests';

    protected int | string | array $columnSpan = 'full';

    public function getRestoreRequests()
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return collect();
        }

        return RestoreRequest::with(['backup', 'approvedBy'])
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'approved' => 'info',
            'rejected' => 'danger',
            'in_progress' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'approved' => 'heroicon-o-check',
            'rejected' => 'heroicon-o-x-mark',
            'in_progress' => 'heroicon-o-arrow-path',
            'completed' => 'heroicon-o-check-circle',
            'failed' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }
}
