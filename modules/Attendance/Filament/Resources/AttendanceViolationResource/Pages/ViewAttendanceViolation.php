<?php

namespace Modules\Attendance\Filament\Resources\AttendanceViolationResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Attendance\Filament\Resources\AttendanceViolationResource;
use Modules\Attendance\Models\AttendanceViolation;

class ViewAttendanceViolation extends ViewRecord
{
    protected static string $resource = AttendanceViolationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label(__('attendance::attendance.approve'))
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->canBeApproved())
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->approve(auth()->user())) {
                        Notification::make()
                            ->title(__('attendance::attendance.violation_approved'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),

            Actions\Action::make('waive')
                ->label(__('attendance::attendance.waive'))
                ->icon('heroicon-o-x-mark')
                ->color('warning')
                ->visible(fn () => $this->record->canBeWaived())
                ->form([
                    \Filament\Forms\Components\Textarea::make('waived_reason')
                        ->label(__('attendance::attendance.waived_reason'))
                        ->required()
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    if ($this->record->waive(auth()->user(), $data['waived_reason'])) {
                        Notification::make()
                            ->title(__('attendance::attendance.violation_waived'))
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
        ];
    }
}
