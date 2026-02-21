<?php

namespace Modules\PatientPortal\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Booking\Models\Appointment;
use Modules\Billing\Models\Invoice;
use Modules\Loyalty\Models\LoyaltyTransaction;

class PortalDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'patientportal::filament.pages.dashboard';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('patientportal::portal.dashboard');
    }

    public function getTitle(): string
    {
        return __('patientportal::portal.welcome', ['name' => $this->getPatient()->first_name]);
    }

    protected function getPatient()
    {
        return Auth::guard('patient')->user();
    }

    protected function getViewData(): array
    {
        $patient = $this->getPatient();

        return [
            'patient' => $patient,
            'upcomingAppointments' => $this->getUpcomingAppointments(),
            'recentInvoices' => $this->getRecentInvoices(),
            'loyaltyPoints' => $this->getLoyaltyPoints(),
            'stats' => $this->getStats(),
        ];
    }

    protected function getUpcomingAppointments(): \Illuminate\Support\Collection
    {
        $patient = $this->getPatient();
        $limit = config('patientportal.display.upcoming_appointments_limit', 5);

        return Appointment::where('patient_id', $patient->id)
            ->where('date', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->with(['treatment', 'practitioner', 'branch'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    protected function getRecentInvoices(): \Illuminate\Support\Collection
    {
        $patient = $this->getPatient();
        $limit = config('patientportal.display.recent_invoices_limit', 5);

        return Invoice::where('patient_id', $patient->id)
            ->with(['appointment.treatment'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    protected function getLoyaltyPoints(): int
    {
        $patient = $this->getPatient();

        if (!class_exists(LoyaltyTransaction::class)) {
            return 0;
        }

        return LoyaltyTransaction::where('patient_id', $patient->id)
            ->sum('points');
    }

    protected function getStats(): array
    {
        $patient = $this->getPatient();

        $totalAppointments = Appointment::where('patient_id', $patient->id)->count();
        $completedAppointments = Appointment::where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->count();

        $totalSpent = Invoice::where('patient_id', $patient->id)
            ->where('status', 'paid')
            ->sum('total_minor');

        $pendingInvoices = Invoice::where('patient_id', $patient->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->sum('total_minor') - Invoice::where('patient_id', $patient->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->sum('paid_amount_minor');

        return [
            [
                'label' => __('patientportal::portal.total_visits'),
                'value' => $completedAppointments,
                'icon' => 'heroicon-o-calendar-days',
                'color' => 'primary',
            ],
            [
                'label' => __('patientportal::portal.loyalty_points'),
                'value' => number_format($this->getLoyaltyPoints()),
                'icon' => 'heroicon-o-star',
                'color' => 'warning',
            ],
            [
                'label' => __('patientportal::portal.total_spent'),
                'value' => number_format($totalSpent / 100, 2) . ' EGP',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
            ],
            [
                'label' => __('patientportal::portal.pending_balance'),
                'value' => number_format($pendingInvoices / 100, 2) . ' EGP',
                'icon' => 'heroicon-o-clock',
                'color' => $pendingInvoices > 0 ? 'danger' : 'success',
            ],
        ];
    }
}
