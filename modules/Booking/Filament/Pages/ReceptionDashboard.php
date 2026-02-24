<?php

namespace Modules\Booking\Filament\Pages;

use App\Services\BranchContext;
use App\Traits\ChecksResourcePermissions;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Modules\Auth\Models\User;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Services\AvailabilityService;
use Modules\Booking\Services\ReceptionService;
use Modules\Core\Models\Room;

class ReceptionDashboard extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reception';

    protected static string $view = 'booking::filament.pages.reception-dashboard';

    public ?string $statusFilter = null;
    public ?string $practitionerFilter = null;
    public ?string $roomFilter = null;

    protected ReceptionService $receptionService;
    protected AvailabilityService $availabilityService;

    public function boot(
        ReceptionService $receptionService,
        AvailabilityService $availabilityService
    ): void {
        $this->receptionService = $receptionService;
        $this->availabilityService = $availabilityService;
    }

    public static function getNavigationLabel(): string
    {
        return __('booking::reception.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::reception.title');
    }

    public function getHeading(): string
    {
        return __('booking::reception.heading') . ' - ' . today()->format('l, M d, Y');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \Modules\Booking\Filament\Widgets\ReceptionStatsWidget::class,
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->hasAnyRole([
            'receptionist',
            'admin',
            'manager',
            'super-admin',
            'super_admin',
            'owner',
            'tenant-owner',
            'tenant_owner',
            'doctor',
            'nurse',
        ]);
    }

    #[Computed]
    public function patientFlow(): array
    {
        return $this->receptionService->getPatientFlowData($this->getBranchId());
    }

    protected function getBranchId(): ?string
    {
        return BranchContext::currentId();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll('15s')
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label(__('booking::reception.columns.time'))
                    ->formatStateUsing(fn ($state) => $state?->format('H:i'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('booking::reception.columns.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn (Appointment $record): string =>
                        $record->patient?->phone ?? ''
                    ),

                Tables\Columns\TextColumn::make('service.name')
                    ->label(__('booking::reception.columns.service'))
                    ->limit(25),

                Tables\Columns\TextColumn::make('practitioner.full_name')
                    ->label(__('booking::reception.columns.doctor'))
                    ->default(__('booking::reception.unassigned'))
                    ->color(fn (Appointment $record): string =>
                        $record->practitioner_id ? 'primary' : 'warning'
                    ),

                Tables\Columns\TextColumn::make('room.name')
                    ->label(__('booking::reception.columns.room'))
                    ->default(__('booking::reception.no_room'))
                    ->color(fn (Appointment $record): string =>
                        $record->room_id ? 'success' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('wait_time')
                    ->label(__('booking::reception.columns.wait_time'))
                    ->getStateUsing(function (Appointment $record): string {
                        $waitTime = $this->receptionService->calculateWaitTime($record);
                        return $waitTime ? $waitTime['formatted'] : '-';
                    })
                    ->color(function (Appointment $record): string {
                        $waitTime = $this->receptionService->calculateWaitTime($record);
                        return $waitTime ? $waitTime['severity'] : 'gray';
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::reception.columns.status'))
                    ->colors([
                        'info' => Appointment::STATUS_SCHEDULED,
                        'primary' => Appointment::STATUS_CONFIRMED,
                        'warning' => Appointment::STATUS_CHECKED_IN,
                        'secondary' => Appointment::STATUS_IN_PROGRESS,
                        'success' => Appointment::STATUS_COMPLETED,
                        'danger' => Appointment::STATUS_CANCELLED,
                        'gray' => Appointment::STATUS_NO_SHOW,
                    ])
                    ->formatStateUsing(fn (string $state): string =>
                        __("booking::reception.statuses.{$state}")
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('booking::reception.filters.status'))
                    ->options([
                        Appointment::STATUS_SCHEDULED => __('booking::reception.statuses.scheduled'),
                        Appointment::STATUS_CONFIRMED => __('booking::reception.statuses.confirmed'),
                        Appointment::STATUS_CHECKED_IN => __('booking::reception.statuses.checked_in'),
                        Appointment::STATUS_IN_PROGRESS => __('booking::reception.statuses.in_progress'),
                        Appointment::STATUS_COMPLETED => __('booking::reception.statuses.completed'),
                        Appointment::STATUS_NO_SHOW => __('booking::reception.statuses.no_show'),
                    ]),

                Tables\Filters\SelectFilter::make('practitioner_id')
                    ->label(__('booking::reception.filters.practitioner'))
                    ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'nurse', 'technician']))
                        ->get()
                        ->pluck('full_name', 'id')
                        ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('room_id')
                    ->label(__('booking::reception.filters.room'))
                    ->options(fn () => $this->getRoomOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('check_in')
                    ->label(__('booking::reception.actions.check_in'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Appointment $record) =>
                        in_array($record->status, [
                            Appointment::STATUS_SCHEDULED,
                            Appointment::STATUS_CONFIRMED,
                        ])
                    )
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => $this->checkInAppointment($record)),

                Tables\Actions\Action::make('assign_room')
                    ->label(__('booking::reception.actions.assign_room'))
                    ->icon('heroicon-o-building-office')
                    ->color('warning')
                    ->visible(fn (Appointment $record) =>
                        $record->isCheckedIn() && !$record->room_id
                    )
                    ->form([
                        Select::make('room_id')
                            ->label(__('booking::reception.forms.room'))
                            ->options(fn (Appointment $record) => $this->getAvailableRoomOptions($record))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(fn (Appointment $record, array $data) =>
                        $this->assignRoom($record, $data['room_id'])
                    ),

                Tables\Actions\Action::make('assign_doctor')
                    ->label(__('booking::reception.actions.assign_doctor'))
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (Appointment $record) =>
                        !$record->practitioner_id && $record->isActive()
                    )
                    ->form([
                        Select::make('practitioner_id')
                            ->label(__('booking::reception.forms.practitioner'))
                            ->options(fn (Appointment $record) => $this->getAvailablePractitionerOptions($record))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(fn (Appointment $record, array $data) =>
                        $this->assignDoctor($record, $data['practitioner_id'])
                    ),

                Tables\Actions\Action::make('start_session')
                    ->label(__('booking::reception.actions.start'))
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->visible(fn (Appointment $record) =>
                        $record->isCheckedIn() && $record->practitioner_id
                    )
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => $this->startSession($record)),

                Tables\Actions\Action::make('mark_no_show')
                    ->label(__('booking::reception.actions.no_show'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $record) =>
                        in_array($record->status, [
                            Appointment::STATUS_SCHEDULED,
                            Appointment::STATUS_CONFIRMED,
                        ]) && $record->start_date_time && $record->start_date_time->isPast()
                    )
                    ->requiresConfirmation()
                    ->action(fn (Appointment $record) => $this->markNoShow($record)),
            ])
            ->defaultSort('start_time', 'asc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Appointment::query()
            ->with(['patient', 'service', 'practitioner', 'room'])
            ->forDate(today())
            ->whereNotIn('status', [
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_RESCHEDULED,
            ]);

        $branchId = $this->getBranchId();
        if ($branchId) {
            $query->forBranch($branchId);
        }

        return $query;
    }

    protected function getRoomOptions(): array
    {
        $branchId = $this->getBranchId();
        if (!$branchId) {
            return [];
        }

        return $this->receptionService->getAllRooms($branchId)
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getAvailableRoomOptions(Appointment $appointment): array
    {
        $branchId = $appointment->branch_id ?? $this->getBranchId();
        if (!$branchId) {
            return [];
        }

        $startTime = $appointment->start_date_time ?? now();
        $endTime = $appointment->end_date_time ?? $startTime->copy()->addMinutes($appointment->duration_minutes ?? 30);

        return $this->receptionService->getAvailableRooms(
            $branchId,
            today(),
            $startTime,
            $endTime
        )->pluck('name', 'id')->toArray();
    }

    protected function getAvailablePractitionerOptions(Appointment $appointment): array
    {
        $branchId = $appointment->branch_id ?? $this->getBranchId();
        if (!$branchId || !$appointment->start_time) {
            return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'nurse', 'technician']))
                ->get()
                ->pluck('full_name', 'id')
                ->toArray();
        }

        $practitioners = $this->availabilityService->getAvailablePractitioners(
            $branchId,
            $appointment->date,
            $appointment->start_time->format('H:i'),
            $appointment->duration_minutes ?? 30
        );

        return $practitioners->pluck('full_name', 'id')->toArray();
    }

    public function checkInAppointment(Appointment $appointment): void
    {
        // If scheduled, confirm first
        if ($appointment->isScheduled()) {
            if (!$appointment->confirm()) {
                Notification::make()
                    ->title(__('booking::reception.messages.cannot_check_in'))
                    ->danger()
                    ->send();
                return;
            }
            $appointment->refresh();
        }

        // Now check in
        if (!$appointment->canTransitionTo(Appointment::STATUS_CHECKED_IN)) {
            Notification::make()
                ->title(__('booking::reception.messages.cannot_check_in'))
                ->danger()
                ->send();
            return;
        }

        $appointment->checkIn();

        Notification::make()
            ->title(__('booking::reception.messages.checked_in'))
            ->body(__('booking::reception.messages.checked_in_body', [
                'patient' => $appointment->patient?->full_name,
            ]))
            ->success()
            ->send();
    }

    public function assignRoom(Appointment $appointment, string $roomId): void
    {
        $room = Room::find($roomId);
        if (!$room) {
            Notification::make()
                ->title(__('booking::reception.messages.room_not_found'))
                ->danger()
                ->send();
            return;
        }

        $appointment->update(['room_id' => $roomId]);

        Notification::make()
            ->title(__('booking::reception.messages.room_assigned'))
            ->body(__('booking::reception.messages.room_assigned_body', [
                'patient' => $appointment->patient?->full_name,
                'room' => $room->name,
            ]))
            ->success()
            ->send();
    }

    public function assignDoctor(Appointment $appointment, string $practitionerId): void
    {
        $practitioner = User::find($practitionerId);
        if (!$practitioner) {
            Notification::make()
                ->title(__('booking::reception.messages.practitioner_not_found'))
                ->danger()
                ->send();
            return;
        }

        $appointment->update(['practitioner_id' => $practitionerId]);

        Notification::make()
            ->title(__('booking::reception.messages.doctor_assigned'))
            ->body(__('booking::reception.messages.doctor_assigned_body', [
                'patient' => $appointment->patient?->full_name,
                'doctor' => $practitioner->full_name,
            ]))
            ->success()
            ->send();
    }

    public function startSession(Appointment $appointment): void
    {
        if (!$appointment->canTransitionTo(Appointment::STATUS_IN_PROGRESS)) {
            Notification::make()
                ->title(__('booking::reception.messages.cannot_start'))
                ->danger()
                ->send();
            return;
        }

        $appointment->start();

        Notification::make()
            ->title(__('booking::reception.messages.session_started'))
            ->body(__('booking::reception.messages.session_started_body', [
                'patient' => $appointment->patient?->full_name,
            ]))
            ->success()
            ->send();
    }

    public function markNoShow(Appointment $appointment): void
    {
        // If scheduled, confirm first (NO_SHOW can only come from CONFIRMED)
        if ($appointment->isScheduled()) {
            if (!$appointment->confirm()) {
                Notification::make()
                    ->title(__('booking::reception.messages.cannot_mark_no_show'))
                    ->danger()
                    ->send();
                return;
            }
            $appointment->refresh();
        }

        if (!$appointment->canTransitionTo(Appointment::STATUS_NO_SHOW)) {
            Notification::make()
                ->title(__('booking::reception.messages.cannot_mark_no_show'))
                ->danger()
                ->send();
            return;
        }

        $appointment->markNoShow();

        Notification::make()
            ->title(__('booking::reception.messages.marked_no_show'))
            ->body(__('booking::reception.messages.marked_no_show_body', [
                'patient' => $appointment->patient?->full_name,
            ]))
            ->warning()
            ->send();
    }

    public function getWaitTimeForAppointment(Appointment $appointment): ?array
    {
        return $this->receptionService->calculateWaitTime($appointment);
    }
}
