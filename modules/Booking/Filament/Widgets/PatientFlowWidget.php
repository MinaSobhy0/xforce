<?php

namespace Modules\Booking\Filament\Widgets;

use App\Services\BranchContext;
use Filament\Widgets\Widget;
use Modules\Booking\Services\ReceptionService;

class PatientFlowWidget extends Widget
{
    protected static string $view = 'booking::filament.widgets.patient-flow';

    protected static ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public function getPatientFlowData(): array
    {
        $receptionService = app(ReceptionService::class);
        $branchId = BranchContext::currentId();

        return $receptionService->getPatientFlowData($branchId);
    }

    public function calculateWaitTime($appointment): ?array
    {
        $receptionService = app(ReceptionService::class);
        return $receptionService->calculateWaitTime($appointment);
    }

    public function getFlowLanes(): array
    {
        return [
            'arriving' => [
                'label' => __('booking::reception.flow.arriving'),
                'icon' => 'heroicon-o-clock',
                'color' => 'info',
                'description' => __('booking::reception.flow.arriving_desc'),
            ],
            'waiting' => [
                'label' => __('booking::reception.flow.waiting'),
                'icon' => 'heroicon-o-user-group',
                'color' => 'warning',
                'description' => __('booking::reception.flow.waiting_desc'),
            ],
            'in_rooms' => [
                'label' => __('booking::reception.flow.in_rooms'),
                'icon' => 'heroicon-o-building-office',
                'color' => 'secondary',
                'description' => __('booking::reception.flow.in_rooms_desc'),
            ],
            'with_doctor' => [
                'label' => __('booking::reception.flow.with_doctor'),
                'icon' => 'heroicon-o-user',
                'color' => 'primary',
                'description' => __('booking::reception.flow.with_doctor_desc'),
            ],
            'done' => [
                'label' => __('booking::reception.flow.done'),
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
                'description' => __('booking::reception.flow.done_desc'),
            ],
        ];
    }
}
