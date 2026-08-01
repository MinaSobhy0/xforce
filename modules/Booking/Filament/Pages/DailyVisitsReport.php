<?php

namespace Modules\Booking\Filament\Pages;

use App\Services\BranchContext;
use App\Traits\ChecksResourcePermissions;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\Visit;

class DailyVisitsReport extends Page implements HasForms, HasTable
{
    use ChecksResourcePermissions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'appointments';
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.rpt_appointments');
    }

    protected static ?int $navigationSort = 30;
    protected static ?string $slug = 'daily-visits-report';

    protected static string $view = 'booking::filament.pages.daily-visits-report';

    #[Url]
    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->selectedDate = $this->selectedDate ?? today()->format('Y-m-d');
    }

    public static function getNavigationLabel(): string
    {
        return __('booking::reports.daily_visits.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::reports.daily_visits.title');
    }

    public function getHeading(): string
    {
        $date = Carbon::parse($this->selectedDate);
        return __('booking::reports.daily_visits.heading', ['date' => $date->format('l, M d, Y')]);
    }

    public function updatedSelectedDate(): void
    {
        $this->resetTable();
    }

    public function goToToday(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
        $this->resetTable();
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
        $this->resetTable();
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
        $this->resetTable();
    }

    public function isToday(): bool
    {
        return Carbon::parse($this->selectedDate)->isToday();
    }

    /**
     * Get summary statistics for the selected date
     */
    public function getSummaryStats(): array
    {
        $branchId = BranchContext::currentId();
        $date = Carbon::parse($this->selectedDate);

        $visits = Visit::query()
            ->with(['appointments.treatmentPlanAppointment', 'appointments.packageSubscription', 'products'])
            ->whereDate('check_in_at', $date)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $totalVisits = $visits->count();
        $completedVisits = $visits->where('status', Visit::STATUS_INVOICED)->count();
        $openVisits = $visits->where('status', Visit::STATUS_OPEN)->count();
        $cancelledVisits = $visits->where('status', Visit::STATUS_CANCELLED)->count();

        // Appointment breakdown
        $allAppointments = $visits->flatMap->appointments;
        $totalAppointments = $allAppointments->count();

        // New sessions (no package, no treatment plan continuation)
        $newSessions = $allAppointments->filter(function ($apt) {
            $isPackage = $apt->is_package_session;
            $isTreatmentPlanContinuation = $apt->treatmentPlanAppointment
                && $apt->treatmentPlanAppointment->session_number > 1;
            return !$isPackage && !$isTreatmentPlanContinuation;
        })->count();

        // Package sessions
        $packageSessions = $allAppointments->where('is_package_session', true)->count();

        // Treatment plan sessions (continuing - session > 1)
        $treatmentPlanContinuations = $allAppointments->filter(function ($apt) {
            return $apt->treatmentPlanAppointment
                && $apt->treatmentPlanAppointment->session_number > 1
                && !$apt->is_package_session;
        })->count();

        // First treatment plan sessions
        $treatmentPlanFirst = $allAppointments->filter(function ($apt) {
            return $apt->treatmentPlanAppointment
                && $apt->treatmentPlanAppointment->session_number === 1
                && !$apt->is_package_session;
        })->count();

        // Revenue
        $totalRevenue = $visits->sum(function ($visit) {
            return $visit->appointments
                ->where('status', Appointment::STATUS_COMPLETED)
                ->where('is_package_session', false)
                ->sum(fn ($apt) => $apt->net_price ?? $apt->price_minor ?? 0);
        });

        $productsRevenue = $visits->sum(function ($visit) {
            return $visit->products->where('usage_type', 'sold')->sum('total_price_minor');
        });

        // Average visit duration (for completed visits)
        $completedWithDuration = $visits->filter(fn ($v) => $v->check_out_at && $v->check_in_at);
        $avgDuration = $completedWithDuration->count() > 0
            ? $completedWithDuration->avg(fn ($v) => $v->check_out_at->diffInMinutes($v->check_in_at))
            : 0;

        return [
            'total_visits' => $totalVisits,
            'completed_visits' => $completedVisits,
            'open_visits' => $openVisits,
            'cancelled_visits' => $cancelledVisits,
            'total_appointments' => $totalAppointments,
            'new_sessions' => $newSessions,
            'package_sessions' => $packageSessions,
            'treatment_plan_continuations' => $treatmentPlanContinuations,
            'treatment_plan_first' => $treatmentPlanFirst,
            'services_revenue' => $totalRevenue,
            'products_revenue' => $productsRevenue,
            'total_revenue' => $totalRevenue + $productsRevenue,
            'avg_duration_minutes' => round($avgDuration),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('booking::visits.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('booking::visits.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_in_at')
                    ->label(__('booking::visits.fields.check_in_at'))
                    ->dateTime('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out_at')
                    ->label(__('booking::visits.fields.check_out_at'))
                    ->dateTime('H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('duration')
                    ->label(__('booking::visits.fields.duration'))
                    ->state(function (Visit $record) {
                        if (!$record->check_out_at) {
                            return $record->check_in_at->diffForHumans(now(), true);
                        }
                        return $record->check_in_at->diffForHumans($record->check_out_at, true);
                    }),

                Tables\Columns\TextColumn::make('session_types')
                    ->label(__('booking::reports.daily_visits.session_types'))
                    ->state(function (Visit $record) {
                        $types = [];
                        foreach ($record->appointments as $apt) {
                            if ($apt->is_package_session) {
                                $types[] = 'package';
                            } elseif ($apt->treatmentPlanAppointment) {
                                $session = $apt->treatmentPlanAppointment->session_number;
                                $types[] = $session > 1 ? 'continuation' : 'plan_first';
                            } else {
                                $types[] = 'new';
                            }
                        }
                        return array_unique($types);
                    })
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return collect($state)->map(fn ($t) => __('booking::reports.daily_visits.types.' . $t))->implode(', ');
                    })
                    ->color(function ($state) {
                        $types = is_array($state) ? $state : [$state];
                        return match (true) {
                            in_array('package', $types) => 'info',
                            in_array('continuation', $types) => 'warning',
                            default => 'success',
                        };
                    }),

                Tables\Columns\TextColumn::make('appointments_summary')
                    ->label(__('booking::reports.daily_visits.services'))
                    ->state(function (Visit $record) {
                        return $record->appointments->map(fn ($apt) => $apt->service?->translated_name)->filter()->implode(', ');
                    })
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('booking::visits.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('booking::visits.statuses.' . $state))
                    ->color(fn ($state) => Visit::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('booking::visits.fields.total'))
                    ->money(fn () => current_currency(), divideBy: 100)
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->money(current_currency(), divideBy: 100)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('booking::visits.fields.status'))
                    ->options(Visit::STATUSES),

                Tables\Filters\SelectFilter::make('source')
                    ->label(__('booking::visits.fields.source'))
                    ->options(Visit::SOURCES),

                Tables\Filters\Filter::make('has_package_sessions')
                    ->label(__('booking::reports.daily_visits.filters.has_package'))
                    ->query(fn (Builder $query) => $query->whereHas('appointments', fn ($q) => $q->where('is_package_session', true))),

                Tables\Filters\Filter::make('has_treatment_plan')
                    ->label(__('booking::reports.daily_visits.filters.has_treatment_plan'))
                    ->query(fn (Builder $query) => $query->whereHas('appointments.treatmentPlanAppointment')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('filament-actions::view.single.label'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (Visit $record) => $record->invoice_id
                        ? \Modules\Billing\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $record->invoice_id])
                        : Checkout::getUrl(['visit_id' => $record->id])
                    ),
            ])
            ->defaultSort('check_in_at', 'asc')
            ->striped();
    }

    protected function getTableQuery(): Builder
    {
        $branchId = BranchContext::currentId();

        return Visit::query()
            ->with([
                'patient',
                'appointments.service',
                'appointments.treatmentPlanAppointment',
                'appointments.packageSubscription',
                'products',
                'invoice',
            ])
            ->whereDate('check_in_at', $this->selectedDate)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }
}
