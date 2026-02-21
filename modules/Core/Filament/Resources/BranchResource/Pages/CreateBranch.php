<?php

namespace Modules\Core\Filament\Resources\BranchResource\Pages;

use Modules\Core\Filament\Resources\BranchResource;
use Modules\Core\Models\Branch;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;

    /**
     * Check if tenant can create more branches before mounting the page.
     */
    public function mount(): void
    {
        $this->authorizeAccess();

        // Check tenant branch limit
        $tenant = app('currentTenant');

        if ($tenant) {
            $currentBranchCount = Branch::count();
            $maxBranches = $tenant->max_branches ?? PHP_INT_MAX;

            if ($currentBranchCount >= $maxBranches) {
                Notification::make()
                    ->title(__('core::core.branch_limit_reached'))
                    ->body(__('core::core.branch_limit_reached_message', [
                        'max' => $maxBranches,
                        'current' => $currentBranchCount,
                    ]))
                    ->danger()
                    ->persistent()
                    ->send();

                $this->redirect(static::getResource()::getUrl('index'));
                return;
            }
        }

        $this->fillForm();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Additional validation before creating.
     */
    protected function beforeCreate(): void
    {
        $tenant = app('currentTenant');

        if ($tenant) {
            $currentBranchCount = Branch::count();
            $maxBranches = $tenant->max_branches ?? PHP_INT_MAX;

            if ($currentBranchCount >= $maxBranches) {
                Notification::make()
                    ->title(__('core::core.branch_limit_reached'))
                    ->body(__('core::core.branch_limit_reached_message', [
                        'max' => $maxBranches,
                        'current' => $currentBranchCount,
                    ]))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default working hours if not provided
        if (empty($data['working_hours'])) {
            $data['working_hours'] = $this->getDefaultWorkingHours();
        }

        return $data;
    }

    protected function getDefaultWorkingHours(): array
    {
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[] = [
                'day' => $day,
                'open_time' => $day === 'friday' ? null : '09:00',
                'close_time' => $day === 'friday' ? null : '18:00',
                'is_closed' => $day === 'friday',
            ];
        }

        return $hours;
    }
}
