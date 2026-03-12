<?php

namespace Modules\Booking\Filament\Pages;

use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;

class DailyAgendaPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner'])) {
            return true;
        }
        return $user->can('daily_agenda.view') || $user->can('daily_agenda.view_any');
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'booking::filament.pages.daily-agenda';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $selectedBranch = null;
    public ?string $selectedPractitioner = null;
    public ?string $selectedDate = null;

    public static function getNavigationLabel(): string
    {
        return __('booking::agenda.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::agenda.title');
    }

    public function mount(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedBranch')
                    ->label(__('booking::agenda.filters.branch'))
                    ->options(Branch::pluck('name', 'id'))
                    ->placeholder(__('booking::agenda.filters.all_branches'))
                    ->live(),

                Select::make('selectedPractitioner')
                    ->label(__('booking::agenda.filters.practitioner'))
                    ->options(function () {
                        return User::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'nurse', 'technician']))
                            ->get()
                            ->pluck('full_name', 'id');
                    })
                    ->placeholder(__('booking::agenda.filters.all_practitioners'))
                    ->live(),

                DatePicker::make('selectedDate')
                    ->label(__('booking::agenda.filters.date'))
                    ->native(false)
                    ->default(today())
                    ->live(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::query()
                    ->with(['patient', 'treatment', 'practitioner', 'branch', 'room'])
                    ->when($this->selectedBranch, fn (Builder $q) => $q->forBranch($this->selectedBranch))
                    ->when($this->selectedPractitioner, fn (Builder $q) => $q->forPractitioner($this->selectedPractitioner))
                    ->forDate($this->selectedDate ? \Carbon\Carbon::parse($this->selectedDate) : today())
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_time')
                    ->label(__('booking::agenda.columns.time'))
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('booking::agenda.columns.patient'))
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('treatment.translated_name')
                    ->label(__('booking::agenda.columns.treatment')),

                Tables\Columns\TextColumn::make('practitioner.full_name')
                    ->label(__('booking::agenda.columns.practitioner')),

                Tables\Columns\TextColumn::make('room.name')
                    ->label(__('booking::agenda.columns.room'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label(__('booking::agenda.columns.duration'))
                    ->suffix(' min'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('booking::agenda.columns.status'))
                    ->colors([
                        'info' => Appointment::STATUS_SCHEDULED,
                        'primary' => Appointment::STATUS_CONFIRMED,
                        'warning' => Appointment::STATUS_CHECKED_IN,
                        'secondary' => Appointment::STATUS_IN_PROGRESS,
                        'success' => Appointment::STATUS_COMPLETED,
                        'danger' => Appointment::STATUS_CANCELLED,
                        'gray' => fn ($state) => in_array($state, [Appointment::STATUS_NO_SHOW, Appointment::STATUS_RESCHEDULED]),
                    ])
                    ->formatStateUsing(fn (string $state): string => Appointment::STATUSES[$state] ?? $state),
            ])
            ->actions([
                Tables\Actions\Action::make('check_in')
                    ->label(__('booking::appointments.actions.check_in'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Appointment $record): bool => $record->canTransitionTo(Appointment::STATUS_CHECKED_IN))
                    ->action(function (Appointment $record) {
                        $record->checkIn();

                        // Check if this is a package session with unpaid balance
                        if ($record->isPackageSession() && $record->packageSubscription) {
                            $subscription = $record->packageSubscription;

                            if ($subscription->hasBalance()) {
                                $packageName = $subscription->package?->getTranslation('name', app()->getLocale()) ?? 'Package';
                                $balance = format_money($subscription->balance_remaining_minor);
                                $patientName = $record->patient?->full_name ?? 'Patient';

                                Notification::make()
                                    ->title(__('booking::appointments.notifications.package_balance_due'))
                                    ->body(__('booking::appointments.notifications.package_balance_message', [
                                        'patient' => $patientName,
                                        'package' => $packageName,
                                        'balance' => $balance,
                                    ]))
                                    ->warning()
                                    ->persistent()
                                    ->actions([
                                        NotificationAction::make('pay')
                                            ->label(__('booking::appointments.actions.pay_balance'))
                                            ->url(route('filament.tenant.resources.package-subscriptions.view', $subscription->id))
                                            ->button()
                                            ->color('success'),
                                        NotificationAction::make('dismiss')
                                            ->label(__('booking::appointments.actions.dismiss'))
                                            ->close(),
                                    ])
                                    ->send();

                                return;
                            }
                        }

                        // Regular check-in success notification
                        Notification::make()
                            ->title(__('booking::appointments.messages.checked_in'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('start')
                    ->label(__('booking::appointments.actions.start'))
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Appointment $record): bool => $record->canTransitionTo(Appointment::STATUS_IN_PROGRESS))
                    ->action(fn (Appointment $record) => $record->start()),

                Tables\Actions\Action::make('complete')
                    ->label(__('booking::appointments.actions.complete'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Appointment $record): bool => $record->canTransitionTo(Appointment::STATUS_COMPLETED))
                    ->action(fn (Appointment $record) => $record->complete()),

                Tables\Actions\Action::make('view')
                    ->label(__('booking::agenda.actions.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (Appointment $record): string => route('filament.tenant.resources.appointments.view', $record)),
            ])
            ->defaultSort('start_time')
            ->poll('30s');
    }

    public function previousDay(): void
    {
        $this->selectedDate = \Carbon\Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
    }

    public function nextDay(): void
    {
        $this->selectedDate = \Carbon\Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
    }

    public function today(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
    }

    public function getStatistics(): array
    {
        $date = $this->selectedDate ? \Carbon\Carbon::parse($this->selectedDate) : today();

        $query = Appointment::query()
            ->forDate($date)
            ->when($this->selectedBranch, fn (Builder $q) => $q->forBranch($this->selectedBranch))
            ->when($this->selectedPractitioner, fn (Builder $q) => $q->forPractitioner($this->selectedPractitioner));

        return [
            'total' => (clone $query)->count(),
            'scheduled' => (clone $query)->byStatus(Appointment::STATUS_SCHEDULED)->count(),
            'confirmed' => (clone $query)->byStatus(Appointment::STATUS_CONFIRMED)->count(),
            'checked_in' => (clone $query)->byStatus(Appointment::STATUS_CHECKED_IN)->count(),
            'in_progress' => (clone $query)->byStatus(Appointment::STATUS_IN_PROGRESS)->count(),
            'completed' => (clone $query)->byStatus(Appointment::STATUS_COMPLETED)->count(),
            'cancelled' => (clone $query)->byStatus(Appointment::STATUS_CANCELLED)->count(),
            'no_show' => (clone $query)->byStatus(Appointment::STATUS_NO_SHOW)->count(),
        ];
    }
}
