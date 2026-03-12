<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Services\BookingRuleEvaluator;
use Modules\Booking\Services\SlotGenerationService;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;
use Modules\Equipment\Models\Equipment;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Models\PackageSessionUsage;
use Modules\Patients\Models\Patient;
use Modules\Services\Models\Service;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;
use Modules\TreatmentPlans\Models\TreatmentPlanAppointment;
use Modules\TreatmentPlans\Services\TreatmentPlanService;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class CreateBooking extends Page implements HasForms
{
    use InteractsWithForms;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'booking::filament.pages.create-booking';

    // Form data
    public ?array $data = [];

    // Slot generation state
    public array $availableSlots = [];
    public array $bookingItems = [];

    // Reschedule tracking
    public ?int $rescheduleAppointmentId = null;
    public ?Appointment $rescheduleAppointment = null;

    public static function getNavigationLabel(): string
    {
        return __('booking::booking.navigation.create_booking');
    }

    public function getTitle(): string
    {
        if ($this->rescheduleAppointment) {
            return __('booking::booking.title.reschedule_booking');
        }
        return __('booking::booking.title.create_booking');
    }

    public function getHeading(): string
    {
        if ($this->rescheduleAppointment) {
            return __('booking::booking.heading.reschedule_booking');
        }
        return __('booking::booking.heading.create_booking');
    }

    public function mount(): void
    {
        // Get query parameters
        $dateFromQuery = request()->query('date');
        $startTimeFromQuery = request()->query('start_time');
        $bookingTypeFromQuery = request()->query('booking_type');
        $treatmentPlanIdFromQuery = request()->query('treatment_plan_id');
        $treatmentPlanItemIdFromQuery = request()->query('treatment_plan_item_id');
        $patientIdFromQuery = request()->query('patient_id');
        $packageSubscriptionIdFromQuery = request()->query('package_subscription_id');
        $rescheduleAppointmentIdFromQuery = request()->query('reschedule_appointment_id');

        $dateFrom = $dateFromQuery ? Carbon::parse($dateFromQuery) : today();
        $bookingType = in_array($bookingTypeFromQuery, ['service', 'package', 'treatment_plan']) ? $bookingTypeFromQuery : 'service';

        // If package subscription is provided, default to package booking type
        if ($packageSubscriptionIdFromQuery && $bookingType === 'service') {
            $bookingType = 'package';
        }

        $formData = [
            'branch_id' => current_branch_id(),
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateFrom->copy()->addWeek()->format('Y-m-d'),
            'booking_type' => $bookingType,
            'services' => [['service_id' => null, 'duration_override' => null, 'price_minor' => null]],
            'source' => Appointment::SOURCE_PHONE,
            'preferred_start_time' => $startTimeFromQuery,
        ];

        // Handle reschedule - load all data from original appointment
        if ($rescheduleAppointmentIdFromQuery) {
            $this->rescheduleAppointmentId = (int) $rescheduleAppointmentIdFromQuery;
            $this->rescheduleAppointment = Appointment::with(['patient', 'service', 'practitioner', 'room', 'packageSubscription.package', 'treatmentPlanAppointment.item.treatmentPlan'])
                ->find($this->rescheduleAppointmentId);

            if ($this->rescheduleAppointment) {
                $appointment = $this->rescheduleAppointment;

                // Set patient
                $formData['patient_id'] = $appointment->patient_id;

                // For reschedule, always use 'service' booking type to show the services repeater
                // But track original treatment plan/package info for proper linking
                $formData['booking_type'] = 'service';

                // Set service data with source tracking
                if ($appointment->service_id) {
                    $serviceData = [
                        'service_id' => $appointment->service_id,
                        'duration_override' => $appointment->duration_minutes,
                        'price_minor' => ($appointment->price_minor ?? 0) / 100, // Convert from piastres to EGP
                    ];

                    // Track package source
                    if ($appointment->is_package_session && $appointment->package_subscription_id) {
                        $formData['package_subscription_id'] = $appointment->package_subscription_id;
                        $formData['package_mode'] = 'existing';
                        $serviceData['source_type'] = 'package';
                        $serviceData['from_package'] = $appointment->package_subscription_id;
                    }
                    // Track treatment plan source
                    elseif ($appointment->treatmentPlanAppointment) {
                        $formData['treatment_plan_id'] = $appointment->treatmentPlanAppointment->item->treatment_plan_id;
                        $formData['treatment_plan_item_id'] = $appointment->treatmentPlanAppointment->treatment_plan_item_id;
                        $serviceData['source_type'] = 'treatment_plan';
                        $serviceData['source_item_id'] = $appointment->treatmentPlanAppointment->treatment_plan_item_id;
                    }

                    $formData['services'] = [$serviceData];
                }

                // Set dates - start from tomorrow for rescheduling
                $formData['date_from'] = today()->format('Y-m-d');
                $formData['date_to'] = today()->addWeeks(2)->format('Y-m-d');

                // Set preferred time from original appointment
                if ($appointment->start_time) {
                    $formData['preferred_start_time'] = $appointment->start_time->format('H:i');
                }

                // Set notes
                if ($appointment->notes) {
                    $formData['notes'] = $appointment->notes;
                }

                // Set source
                $formData['source'] = Appointment::SOURCE_RESCHEDULED;
            }
        }

        // Handle patient from query (if not already set by reschedule)
        if ($patientIdFromQuery && !isset($formData['patient_id'])) {
            $formData['patient_id'] = $patientIdFromQuery;
        }

        // Handle package subscription from query
        if ($packageSubscriptionIdFromQuery && !$this->rescheduleAppointment) {
            $subscription = PackageSubscription::with('package')->find($packageSubscriptionIdFromQuery);
            if ($subscription && $subscription->isActive()) {
                $formData['package_subscription_id'] = $packageSubscriptionIdFromQuery;
                $formData['package_mode'] = 'existing';
                // Also set patient if not already set
                if (!isset($formData['patient_id'])) {
                    $formData['patient_id'] = $subscription->patient_id;
                }
            }
        }

        // Handle treatment plan booking (if not already set by reschedule)
        if ($bookingType === 'treatment_plan' && $treatmentPlanIdFromQuery && !$this->rescheduleAppointment) {
            $treatmentPlan = TreatmentPlan::with(['patient', 'items.service'])->find($treatmentPlanIdFromQuery);

            if ($treatmentPlan) {
                $formData['patient_id'] = $treatmentPlan->patient_id;
                $formData['treatment_plan_id'] = $treatmentPlan->id;

                // If specific item is selected, use it; otherwise find first bookable
                if ($treatmentPlanItemIdFromQuery) {
                    $item = $treatmentPlan->items->firstWhere('id', $treatmentPlanItemIdFromQuery);
                } else {
                    $item = $treatmentPlan->items->first(fn ($i) => $i->canBook());
                }

                if ($item) {
                    $formData['treatment_plan_item_id'] = $item->id;

                    // Set suggested dates based on item's next suggested date
                    if ($item->next_suggested_date) {
                        $formData['date_from'] = $item->next_suggested_date->format('Y-m-d');
                        $formData['date_to'] = $item->next_suggested_date->copy()->addWeeks(2)->format('Y-m-d');
                    }
                }
            }
        }

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Hidden fields for package state - MUST be outside conditional sections
                Forms\Components\Hidden::make('_package_mode'),
                Forms\Components\Hidden::make('_package_subscription_id'),
                Forms\Components\Hidden::make('_new_package_id'),

                // Main content area - two columns
                Forms\Components\Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        // Left Column - Main Form (2/3 width)
                        Forms\Components\Group::make()
                            ->schema([
                                // Patient & Booking Type Section
                                Forms\Components\Section::make(__('booking::booking.sections.patient_service'))
                                    ->description(__('booking::booking.sections.patient_service_desc'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                // Patient Selection
                                                Forms\Components\Select::make('patient_id')
                                                    ->label(__('booking::booking.fields.patient'))
                                                    ->options(function () {
                                                        return Patient::query()
                                                            ->orderBy('first_name')
                                                            ->limit(50)
                                                            ->get()
                                                            ->mapWithKeys(fn (Patient $p) => [
                                                                $p->id => "{$p->full_name} ({$p->code})"
                                                            ]);
                                                    })
                                                    ->searchable()
                                                    ->getSearchResultsUsing(function (string $search): array {
                                                        return Patient::query()
                                                            ->where(function ($q) use ($search) {
                                                                $q->where('first_name', 'ilike', "%{$search}%")
                                                                    ->orWhere('last_name', 'ilike', "%{$search}%")
                                                                    ->orWhere('phone', 'ilike', "%{$search}%")
                                                                    ->orWhere('code', 'ilike', "%{$search}%");
                                                            })
                                                            ->limit(20)
                                                            ->get()
                                                            ->mapWithKeys(fn (Patient $p) => [
                                                                $p->id => "{$p->full_name} ({$p->code}) - {$p->phone}"
                                                            ])
                                                            ->toArray();
                                                    })
                                                    ->required()
                                                    ->live()
                                                    ->createOptionForm([
                                                        Forms\Components\Grid::make(2)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('first_name')
                                                                    ->label(__('patients::patients.fields.first_name'))
                                                                    ->required()
                                                                    ->maxLength(100),
                                                                Forms\Components\TextInput::make('last_name')
                                                                    ->label(__('patients::patients.fields.last_name'))
                                                                    ->required()
                                                                    ->maxLength(100),
                                                            ]),
                                                        Forms\Components\TextInput::make('phone')
                                                            ->label(__('patients::patients.fields.phone'))
                                                            ->required()
                                                            ->tel()
                                                            ->maxLength(20),
                                                        Forms\Components\TextInput::make('email')
                                                            ->label(__('patients::patients.fields.email'))
                                                            ->email()
                                                            ->maxLength(255),
                                                    ])
                                                    ->createOptionUsing(function (array $data): string {
                                                        $patient = Patient::create($data);
                                                        return $patient->id;
                                                    }),

                                                // Booking Type
                                                Forms\Components\ToggleButtons::make('booking_type')
                                                    ->label(__('booking::booking.fields.booking_type'))
                                                    ->options([
                                                        'service' => __('booking::booking.booking_types.service'),
                                                        'package' => __('booking::booking.booking_types.package'),
                                                        'treatment_plan' => __('booking::booking.booking_types.treatment_plan'),
                                                    ])
                                                    ->icons([
                                                        'service' => 'heroicon-o-sparkles',
                                                        'package' => 'heroicon-o-gift',
                                                        'treatment_plan' => 'heroicon-o-clipboard-document-list',
                                                    ])
                                                    ->default('service')
                                                    ->inline()
                                                    ->live()
                                                    ->required(),
                                            ]),

                                        // Patient Info Card - shows clickable packages and treatment plans
                                        Forms\Components\Placeholder::make('patient_info')
                                            ->label('')
                                            ->content(function (Get $get) {
                                                $patientId = $get('patient_id');
                                                $currentBookingType = $get('booking_type');
                                                $selectedSubscriptionId = $get('package_subscription_id');
                                                $selectedPlanId = $get('treatment_plan_id');

                                                if (!$patientId) {
                                                    return '';
                                                }

                                                try {
                                                    $patient = Patient::find($patientId);
                                                    if (!$patient) {
                                                        return '';
                                                    }

                                                    // Get active packages
                                                    $activePackages = PackageSubscription::query()
                                                        ->forPatient($patientId)
                                                        ->active()
                                                        ->with(['package.items.service'])
                                                        ->get();

                                                    // Get active treatment plans
                                                    $activePlans = collect([]);
                                                    try {
                                                        $activePlans = TreatmentPlan::query()
                                                            ->forPatient($patientId)
                                                            ->active()
                                                            ->with(['items.service'])
                                                            ->get();
                                                    } catch (\Exception $e) {
                                                        // Treatment plans table may not exist
                                                    }

                                                    // Hide section if no packages and no treatment plans
                                                    if ($activePackages->isEmpty() && $activePlans->isEmpty()) {
                                                        return '';
                                                    }

                                                    $html = '<div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800">';

                                                    // Two-column layout: Left = Packages & Plans list, Right = Selected details
                                                    $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">';

                                                    // LEFT COLUMN: Packages and Treatment Plans stacked (only show sections with content)
                                                    $html .= '<div>';

                                                    // Packages section (only if there are packages)
                                                    if ($activePackages->isNotEmpty()) {
                                                        $html .= '<div class="mb-3">';
                                                        $html .= '<div class="flex items-center gap-2 mb-2">';
                                                        $html .= '<svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>';
                                                        $html .= '<span class="font-semibold text-sm text-gray-900 dark:text-white">' . __('booking::booking.labels.active_packages') . '</span>';
                                                        $html .= '<span class="ml-auto px-2 py-0.5 text-xs font-medium rounded-full" style="background-color: #dcfce7; color: #15803d;">' . $activePackages->count() . '</span>';
                                                        $html .= '</div>';
                                                        $html .= '<div class="flex flex-wrap gap-1.5">';
                                                        foreach ($activePackages as $sub) {
                                                            $isSelected = $currentBookingType === 'package' && $selectedSubscriptionId === $sub->id;
                                                            $pillStyle = $isSelected
                                                                ? 'background-color: #22c55e; color: white; box-shadow: 0 0 0 2px #86efac;'
                                                                : 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;';
                                                            $badgeStyle = $isSelected
                                                                ? 'background-color: #4ade80; color: white;'
                                                                : 'background-color: #e5e7eb; color: #374151;';

                                                            $html .= '<button type="button" wire:click="selectPackageForBooking(\'' . $sub->id . '\')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all cursor-pointer" style="' . $pillStyle . '">';
                                                            if ($isSelected) {
                                                                $html .= '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                                                            }
                                                            $html .= '<span class="truncate max-w-[100px]">' . e($sub->package->translated_name) . '</span>';
                                                            // Show pulses or sessions based on package type
                                                            // Check if package is pulse-based (by consumption_type or pulses_per_session)
                                                            $isPulse = $sub->package->isPulseBased() || $sub->package->hasPulseBasedItems();
                                                            if ($isPulse) {
                                                                $totalPulses = $sub->package->total_pulses;
                                                                // If total_pulses is 0, calculate from items manually
                                                                if ($totalPulses <= 0) {
                                                                    $totalPulses = $sub->package->items->sum(fn ($item) => ($item->quantity ?? 0) * ($item->pulses_per_session ?? 1));
                                                                }
                                                                // Use pulses_used directly - it sums quantity_used from usage records
                                                                $pulsesUsed = $sub->pulses_used;
                                                                $pulsesRemaining = max(0, $totalPulses - $pulsesUsed);
                                                                $html .= '<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" style="' . $badgeStyle . '">' . number_format($pulsesRemaining) . '/' . number_format($totalPulses) . ' ' . __('packages::packages.labels.pulses') . '</span>';
                                                            } else {
                                                                $html .= '<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" style="' . $badgeStyle . '">' . $sub->sessions_remaining . '/' . $sub->package->total_sessions . '</span>';
                                                            }
                                                            $html .= '</button>';
                                                        }
                                                        $html .= '</div>';
                                                        $html .= '</div>';
                                                    }

                                                    // Treatment Plans section (only if there are plans)
                                                    if ($activePlans->isNotEmpty()) {
                                                        $html .= '<div>';
                                                        $html .= '<div class="flex items-center gap-2 mb-2">';
                                                        $html .= '<svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>';
                                                        $html .= '<span class="font-semibold text-sm text-gray-900 dark:text-white">' . __('booking::booking.labels.active_treatment_plans') . '</span>';
                                                        $html .= '<span class="ml-auto px-2 py-0.5 text-xs font-medium rounded-full" style="background-color: #dbeafe; color: #1d4ed8;">' . $activePlans->count() . '</span>';
                                                        $html .= '</div>';
                                                        $html .= '<div class="flex flex-wrap gap-1.5">';
                                                        foreach ($activePlans as $plan) {
                                                            // Check if this plan is selected (regardless of booking_type since we switch to 'service' mode)
                                                            $isSelected = $selectedPlanId && (string) $selectedPlanId === (string) $plan->id;
                                                            $remainingSessions = $plan->total_recommended_sessions - $plan->total_completed_sessions;
                                                            $progress = round($plan->progress_percentage);
                                                            $pillStyle = $isSelected
                                                                ? 'background-color: #22c55e; color: white; box-shadow: 0 0 0 2px #86efac;'
                                                                : 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;';
                                                            $badgeStyle = $isSelected
                                                                ? 'background-color: #4ade80; color: white;'
                                                                : 'background-color: #e5e7eb; color: #374151;';

                                                            $html .= '<button type="button" wire:click="selectTreatmentPlanForBooking(\'' . $plan->id . '\')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all cursor-pointer" style="' . $pillStyle . '">';
                                                            if ($isSelected) {
                                                                $html .= '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                                                            }
                                                            $html .= '<span class="truncate max-w-[100px]">' . e($plan->translated_name) . '</span>';
                                                            $html .= '<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" style="' . $badgeStyle . '">' . $remainingSessions . ' · ' . $progress . '%</span>';
                                                            $html .= '</button>';
                                                        }
                                                        $html .= '</div>';
                                                        $html .= '</div>';
                                                    }

                                                    $html .= '</div>'; // Close left column

                                                    // RIGHT COLUMN: Selected package/plan services
                                                    $html .= '<div style="border-left: 1px solid #e5e7eb; padding-left: 1rem;">';

                                                    if ($currentBookingType === 'package' && $selectedSubscriptionId) {
                                                        // Show selected package services
                                                        $selectedSub = $activePackages->firstWhere('id', $selectedSubscriptionId);
                                                        if ($selectedSub) {
                                                            $html .= '<div class="flex items-center gap-2 mb-2">';
                                                            $html .= '<span class="font-semibold text-sm text-gray-900 dark:text-white">' . __('booking::booking.labels.select_services_to_book') . '</span>';
                                                            $html .= '</div>';
                                                            $html .= '<div class="flex flex-wrap gap-1.5">';

                                                            $selectedPackageServiceId = $this->data['package_service_id'] ?? null;
                                                            foreach ($selectedSub->package->items as $item) {
                                                                $remaining = $selectedSub->getSessionsRemainingByService($item->service_id);
                                                                if ($remaining > 0) {
                                                                    $isServiceSelected = $selectedPackageServiceId === $item->service_id;
                                                                    $svcPillStyle = $isServiceSelected
                                                                        ? 'background-color: #22c55e; color: white; box-shadow: 0 0 0 2px #86efac;'
                                                                        : 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;';
                                                                    $svcBadgeStyle = $isServiceSelected
                                                                        ? 'background-color: #4ade80; color: white;'
                                                                        : 'background-color: #e5e7eb; color: #374151;';

                                                                    $html .= '<button type="button" wire:click="$set(\'data.package_service_id\', \'' . $item->service_id . '\')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all cursor-pointer" style="' . $svcPillStyle . '">';
                                                                    if ($isServiceSelected) {
                                                                        $html .= '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                                                                    }
                                                                    $html .= '<span class="truncate max-w-[120px]">' . e($item->service->translated_name) . '</span>';
                                                                    $html .= '<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" style="' . $svcBadgeStyle . '">' . $remaining . '/' . $item->quantity . '</span>';
                                                                    $html .= '</button>';
                                                                }
                                                            }
                                                            $html .= '</div>';
                                                        }
                                                    } elseif ($selectedPlanId) {
                                                        // Show selected treatment plan items (regardless of booking_type)
                                                        $selectedPlan = $activePlans->firstWhere('id', $selectedPlanId);
                                                        if ($selectedPlan) {
                                                            $html .= '<div class="flex items-center gap-2 mb-2">';
                                                            $html .= '<span class="font-semibold text-sm text-gray-900 dark:text-white">' . __('booking::booking.labels.select_services_to_book') . '</span>';
                                                            $html .= '</div>';
                                                            $html .= '<div class="flex flex-wrap gap-1.5">';

                                                            $selectedItemId = $this->data['treatment_plan_item_id'] ?? null;
                                                            foreach ($selectedPlan->items as $item) {
                                                                // Show all items with service, not just bookable ones
                                                                if (!$item->service || $item->isCompleted() || $item->isCancelled()) {
                                                                    continue;
                                                                }

                                                                $isItemSelected = (string) $selectedItemId === (string) $item->id;
                                                                $canBook = $item->canBook();
                                                                $hasScheduledAppointment = $item->scheduled_sessions_count > 0;
                                                                $nextDate = $item->next_suggested_date ? $item->next_suggested_date->format('M d') : '-';

                                                                // Get scheduled appointment date if exists
                                                                $scheduledDate = null;
                                                                if ($hasScheduledAppointment) {
                                                                    $scheduledAppt = $item->planAppointments()
                                                                        ->whereHas('appointment', function ($q) {
                                                                            $q->whereIn('status', [
                                                                                Appointment::STATUS_SCHEDULED,
                                                                                Appointment::STATUS_CONFIRMED,
                                                                                Appointment::STATUS_CHECKED_IN,
                                                                            ]);
                                                                        })
                                                                        ->with('appointment')
                                                                        ->first();
                                                                    if ($scheduledAppt?->appointment?->date) {
                                                                        $scheduledDate = $scheduledAppt->appointment->date->format('M d');
                                                                    }
                                                                }

                                                                // Different styles based on selection and booking status
                                                                if ($isItemSelected) {
                                                                    $itemPillStyle = 'background-color: #22c55e; color: white; box-shadow: 0 0 0 2px #86efac;';
                                                                    $itemBadgeStyle = 'background-color: #4ade80; color: white;';
                                                                } elseif ($hasScheduledAppointment && !$canBook) {
                                                                    $itemPillStyle = 'background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d;';
                                                                    $itemBadgeStyle = 'background-color: #fde68a; color: #92400e;';
                                                                } else {
                                                                    $itemPillStyle = 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;';
                                                                    $itemBadgeStyle = 'background-color: #e5e7eb; color: #374151;';
                                                                }

                                                                $html .= '<button type="button" wire:click="selectTreatmentPlanItem(\'' . $item->id . '\')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all cursor-pointer" style="' . $itemPillStyle . '">';
                                                                if ($isItemSelected) {
                                                                    $html .= '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                                                                } elseif ($hasScheduledAppointment) {
                                                                    $html .= '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>';
                                                                }
                                                                $html .= '<span class="truncate max-w-[120px]">' . e($item->service->translated_name) . '</span>';
                                                                $html .= '<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" style="' . $itemBadgeStyle . '">' . $item->remaining_sessions . '/' . $item->recommended_sessions . '</span>';
                                                                if ($scheduledDate) {
                                                                    $html .= '<span class="text-[10px] opacity-75">' . $scheduledDate . '</span>';
                                                                } else {
                                                                    $html .= '<span class="text-[10px] opacity-75">' . $nextDate . '</span>';
                                                                }
                                                                $html .= '</button>';
                                                            }
                                                            $html .= '</div>';
                                                        }
                                                    } else {
                                                        // No selection - show hint
                                                        $html .= '<div class="flex items-center justify-center h-full text-sm text-gray-400">';
                                                        $html .= '<div class="text-center">';
                                                        $html .= '<svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
                                                        $html .= '<p>' . __('booking::booking.labels.click_to_book') . '</p>';
                                                        $html .= '</div>';
                                                        $html .= '</div>';
                                                    }

                                                    $html .= '</div>'; // Close right column

                                                    $html .= '</div>'; // Close grid

                                                    $html .= '</div>';

                                                    return new HtmlString($html);
                                                } catch (\Exception $e) {
                                                    return '';
                                                }
                                            })
                                            ->visible(fn (Get $get) => $get('patient_id'))
                                            ->columnSpanFull(),

                                        // Service Selection (for service booking)
                                        Forms\Components\Section::make(__('booking::booking.fields.services'))
                                            ->schema([
                                                Forms\Components\Repeater::make('services')
                                                    ->label('')
                                                    ->schema([
                                                        // Row 1: Service, Duration, Slot info, Select Slot button
                                                        Forms\Components\Grid::make(12)
                                                            ->schema([
                                                                Forms\Components\Select::make('service_id')
                                                                    ->label(__('booking::booking.fields.service'))
                                                                    ->options(function () {
                                                                        return Service::query()
                                                                            ->active()
                                                                            ->ordered()
                                                                            ->get()
                                                                            ->mapWithKeys(fn (Service $s) => [
                                                                                $s->id => "{$s->translated_name} ({$s->duration_minutes} min)"
                                                                            ]);
                                                                    })
                                                                    ->searchable()
                                                                    ->preload()
                                                                    ->required()
                                                                    ->live()
                                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                                        if ($state) {
                                                                            $service = Service::find($state);
                                                                            if ($service) {
                                                                                $set('duration_override', $service->duration_minutes);
                                                                                // Convert from piastres to EGP for display
                                                                                $set('price_minor', ($service->base_price_minor ?? 0) / 100);
                                                                                $set('discount_minor', 0);
                                                                                $set('max_discount_percent', $service->max_discount_percent ?? 100);
                                                                            }
                                                                        }
                                                                    })
                                                                    // Package/treatment plan services cannot be changed
                                                                    ->disabled(fn (Get $get) => in_array($get('source_type'), ['package', 'treatment_plan']))
                                                                    ->columnSpan(4),

                                                                Forms\Components\TextInput::make('duration_override')
                                                                    ->label(__('booking::booking.fields.duration'))
                                                                    ->numeric()
                                                                    ->suffix(__('booking::booking.minutes'))
                                                                    ->columnSpan(2),

                                                                // Slot info display
                                                                Forms\Components\Placeholder::make('slot_info')
                                                                    ->label('')
                                                                    ->content(function (Get $get, $livewire) {
                                                                        $serviceId = $get('service_id');
                                                                        if (!$serviceId) {
                                                                            return new HtmlString('<span class="text-gray-400 text-sm">' . __('booking::booking.messages.select_service_first') . '</span>');
                                                                        }

                                                                        // Check if this service has a booked slot
                                                                        foreach ($livewire->bookingItems as $item) {
                                                                            if ((string) ($item['service_id'] ?? '') === (string) $serviceId) {
                                                                                $date = Carbon::parse($item['date'])->format('M d');
                                                                                $time = $item['start_time'];
                                                                                $practitioner = $item['practitioner_name'] ?? '';
                                                                                return new HtmlString(
                                                                                    '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-green-700 bg-green-100 text-sm">' .
                                                                                    '<svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>' .
                                                                                    $date . ' ' . $time .
                                                                                    ($practitioner ? ' - ' . $practitioner : '') .
                                                                                    '</span>'
                                                                                );
                                                                            }
                                                                        }

                                                                        return new HtmlString(
                                                                            '<span class="text-amber-600 text-sm">' . __('booking::booking.messages.no_slot_selected') . '</span>'
                                                                        );
                                                                    })
                                                                    ->columnSpan(4),

                                                                // Select Slot Button
                                                                Forms\Components\Actions::make([
                                                                    Forms\Components\Actions\Action::make('select_slot')
                                                                        ->label(__('booking::booking.actions.select_slot'))
                                                                        ->icon('heroicon-o-calendar')
                                                                        ->color('primary')
                                                                        ->size('sm')
                                                                        ->action(function (array $arguments, $livewire, $component) {
                                                                            // Get service_id from the repeater item state
                                                                            $repeaterState = $component->getContainer()->getParentComponent()->getState();
                                                                            $serviceId = $repeaterState['service_id'] ?? null;
                                                                            $durationOverride = $repeaterState['duration_override'] ?? null;
                                                                            if ($serviceId) {
                                                                                $livewire->generateSlotsForService($serviceId, $durationOverride);
                                                                            }
                                                                        }),
                                                                ])
                                                                ->columnSpan(2)
                                                                ->visible(fn (Get $get): bool => filled($get('service_id'))),
                                                            ]),

                                                        // Row 2: Price, Discount, Total, Source indicator
                                                        Forms\Components\Grid::make(12)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('price_minor')
                                                                    ->label(__('booking::booking.fields.price'))
                                                                    ->numeric()
                                                                    ->prefix(current_currency())
                                                                    ->live(onBlur: true)
                                                                    // Hide for package services (price is at package level)
                                                                    ->hidden(fn (Get $get) => $get('source_type') === 'package')
                                                                    ->columnSpan(2),

                                                                Forms\Components\TextInput::make('discount_minor')
                                                                    ->label(__('booking::booking.fields.discount'))
                                                                    ->numeric()
                                                                    ->prefix(current_currency())
                                                                    ->default(0)
                                                                    ->live(onBlur: true)
                                                                    ->helperText(function (Get $get) {
                                                                        $maxPercent = (float) ($get('max_discount_percent') ?? 100);
                                                                        $price = (float) ($get('price_minor') ?? 0);
                                                                        if ($maxPercent < 100 && $price > 0) {
                                                                            $maxAmount = ($price * $maxPercent) / 100;
                                                                            return __('booking::booking.fields.max_discount') . ': ' . $maxPercent . '% (' . number_format($maxAmount, 2) . ')';
                                                                        }
                                                                        return null;
                                                                    })
                                                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                                        // Validate discount doesn't exceed max
                                                                        $price = (float) ($get('price_minor') ?? 0);
                                                                        $maxPercent = (float) ($get('max_discount_percent') ?? 100);
                                                                        $discount = (float) ($state ?? 0);
                                                                        $maxDiscount = ($price * $maxPercent) / 100;

                                                                        if ($discount > $maxDiscount && $maxPercent < 100) {
                                                                            $set('discount_minor', $maxDiscount);
                                                                            Notification::make()
                                                                                ->title(__('booking::booking.validation.discount_exceeds_max', [
                                                                                    'max' => $maxPercent,
                                                                                    'amount' => number_format($maxDiscount, 2),
                                                                                ]))
                                                                                ->warning()
                                                                                ->duration(3000)
                                                                                ->send();
                                                                        }
                                                                    })
                                                                    // Hide for package services (no discount for packages)
                                                                    ->hidden(fn (Get $get) => $get('source_type') === 'package')
                                                                    ->columnSpan(2),

                                                                // Total (calculated) - hide for packages
                                                                Forms\Components\Placeholder::make('total_display')
                                                                    ->label(__('booking::booking.fields.total'))
                                                                    ->content(function (Get $get) {
                                                                        $price = (float) ($get('price_minor') ?? 0);
                                                                        $discount = (float) ($get('discount_minor') ?? 0);
                                                                        $total = max(0, $price - $discount);
                                                                        return new HtmlString(
                                                                            '<span class="font-semibold text-lg">' .
                                                                            number_format($total, 2) . ' ' . current_currency() .
                                                                            '</span>'
                                                                        );
                                                                    })
                                                                    ->hidden(fn (Get $get) => $get('source_type') === 'package')
                                                                    ->columnSpan(2),

                                                                // Source indicator (treatment plan / package / manual)
                                                                Forms\Components\Placeholder::make('source_info')
                                                                    ->label('')
                                                                    ->content(function (Get $get) {
                                                                        $sourceType = $get('source_type');
                                                                        $existingDate = $get('existing_appointment_date');
                                                                        $existingTime = $get('existing_appointment_time');
                                                                        $packageSessions = $get('package_sessions');
                                                                        $packageSessionsRemaining = $get('package_sessions_remaining');
                                                                        $consumptionType = $get('package_consumption_type');
                                                                        $pulsesPerSession = $get('package_pulses_per_session');

                                                                        $html = '';

                                                                        // Source badge with sessions/pulses info
                                                                        if ($sourceType === 'treatment_plan') {
                                                                            $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-2">' .
                                                                                '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>' .
                                                                                __('booking::booking.labels.from_treatment_plan') .
                                                                                '</span>';
                                                                        } elseif ($sourceType === 'package') {
                                                                            // Package badge
                                                                            $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 mr-2">' .
                                                                                '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>' .
                                                                                __('booking::booking.labels.from_package') .
                                                                                '</span>';

                                                                            // Sessions/Pulses badge
                                                                            if ($packageSessions) {
                                                                                $remaining = $packageSessionsRemaining ?? $packageSessions;
                                                                                $unitLabel = $consumptionType === 'pulses' ? __('booking::booking.labels.pulses') : __('booking::booking.labels.sessions');

                                                                                // Calculate total pulses if pulse-based
                                                                                if ($consumptionType === 'pulses' && $pulsesPerSession) {
                                                                                    $totalPulses = $packageSessions * $pulsesPerSession;
                                                                                    $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 mr-2">' .
                                                                                        $packageSessions . ' ' . __('booking::booking.labels.sessions') . ' × ' . $pulsesPerSession . ' = ' . $totalPulses . ' ' . $unitLabel .
                                                                                        '</span>';
                                                                                } else {
                                                                                    $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 mr-2">' .
                                                                                        $remaining . '/' . $packageSessions . ' ' . $unitLabel .
                                                                                        '</span>';
                                                                                }
                                                                            }
                                                                        }

                                                                        // Existing appointment warning
                                                                        if ($existingDate) {
                                                                            $date = Carbon::parse($existingDate)->format('M d, Y');
                                                                            $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">' .
                                                                                '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>' .
                                                                                __('booking::booking.labels.already_booked') . ': ' . $date . ' ' . ($existingTime ?? '') .
                                                                                '</span>';
                                                                        }

                                                                        return $html ? new HtmlString($html) : '';
                                                                    })
                                                                    ->columnSpan(6),
                                                            ])
                                                            ->visible(fn (Get $get): bool => filled($get('service_id'))),

                                                        // Hidden fields for tracking
                                                        Forms\Components\Hidden::make('max_discount_percent'),
                                                        Forms\Components\Hidden::make('source_type'),
                                                        Forms\Components\Hidden::make('source_item_id'),
                                                        Forms\Components\Hidden::make('package_sessions'),
                                                        Forms\Components\Hidden::make('package_sessions_remaining'),
                                                        Forms\Components\Hidden::make('package_consumption_type'),
                                                        Forms\Components\Hidden::make('package_pulses_per_session'),
                                                        Forms\Components\Hidden::make('existing_appointment_id'),
                                                        Forms\Components\Hidden::make('existing_appointment_date'),
                                                        Forms\Components\Hidden::make('existing_appointment_time'),
                                                        // Package IDs stored per service
                                                        Forms\Components\Hidden::make('from_package'),
                                                        Forms\Components\Hidden::make('new_package_id'),
                                                    ])
                                                    ->addActionLabel(__('booking::booking.actions.add_service'))
                                                    ->deleteAction(
                                                        fn ($action) => $action->after(function ($livewire) {
                                                            // Sync booking items with current services
                                                            $livewire->syncBookingItemsWithServices();
                                                        })
                                                    )
                                                    ->minItems(1)
                                                    ->maxItems(10)
                                                    ->reorderable(false)
                                                    ->defaultItems(1)
                                                    ->live()
                                                    ->itemLabel(fn (array $state): ?string =>
                                                        isset($state['service_id'])
                                                            ? Service::find($state['service_id'])?->translated_name
                                                            : null
                                                    )
                                                    ->columnSpanFull(),

                                                // Cart Total
                                                Forms\Components\Placeholder::make('cart_total')
                                                    ->label('')
                                                    ->content(function (Get $get) {
                                                        $services = $get('services') ?? [];
                                                        $newPackageId = $get('new_package_id');
                                                        $packageSubscriptionId = $get('package_subscription_id');

                                                        // Separate package items from regular services
                                                        $hasPackageItems = false;
                                                        $hasNewPackage = false;
                                                        $hasExistingPackage = false;
                                                        $servicesTotal = 0;
                                                        $packagePrice = 0;

                                                        foreach ($services as $service) {
                                                            if (($service['source_type'] ?? null) === 'package') {
                                                                $hasPackageItems = true;
                                                            } else {
                                                                // Regular service - add to total
                                                                $price = (float) ($service['price_minor'] ?? 0);
                                                                $discount = (float) ($service['discount_minor'] ?? 0);
                                                                $servicesTotal += max(0, $price - $discount);
                                                            }
                                                        }

                                                        // Get package price (convert from piastres to EGP)
                                                        if ($hasPackageItems && $newPackageId) {
                                                            $hasNewPackage = true;
                                                            $package = Package::find($newPackageId);
                                                            if ($package) {
                                                                $packagePrice = ($package->effective_price_minor ?? 0) / 100;
                                                            }
                                                        } elseif ($hasPackageItems && $packageSubscriptionId) {
                                                            $hasExistingPackage = true;
                                                            // Get the subscription's purchase price
                                                            $subscription = PackageSubscription::find($packageSubscriptionId);
                                                            if ($subscription) {
                                                                $packagePrice = ($subscription->package_price_minor ?? 0) / 100;
                                                            }
                                                        }

                                                        // Build HTML output
                                                        $html = '<div class="border-t pt-3 mt-2 space-y-1">';

                                                        // Show package line if applicable
                                                        if ($hasNewPackage || $hasExistingPackage) {
                                                            $priceLabel = $hasExistingPackage
                                                                ? __('booking::booking.labels.package_price') . ' <span class="text-green-600 text-xs">(' . __('booking::booking.labels.prepaid') . ')</span>'
                                                                : __('booking::booking.labels.package_price');
                                                            $html .= '<div class="flex justify-between text-sm">' .
                                                                '<span class="text-gray-600">' . $priceLabel . ':</span>' .
                                                                '<span class="font-medium">' . number_format($packagePrice, 2) . ' ' . current_currency() . '</span>' .
                                                                '</div>';
                                                        }

                                                        // Show services line if there are regular services
                                                        if ($servicesTotal > 0) {
                                                            $html .= '<div class="flex justify-between text-sm">' .
                                                                '<span class="text-gray-600">' . __('booking::booking.labels.services_total') . ':</span>' .
                                                                '<span class="font-medium">' . number_format($servicesTotal, 2) . ' ' . current_currency() . '</span>' .
                                                                '</div>';
                                                        }

                                                        // Grand total
                                                        $grandTotal = $packagePrice + $servicesTotal;
                                                        $html .= '<div class="flex justify-between pt-2 border-t mt-2">' .
                                                            '<span class="text-gray-600 font-medium">' . __('booking::booking.labels.cart_total') . ':</span>' .
                                                            '<span class="font-bold text-xl text-primary-600">' . number_format($grandTotal, 2) . ' ' . current_currency() . '</span>' .
                                                            '</div>';

                                                        $html .= '</div>';

                                                        return new HtmlString($html);
                                                    })
                                                    ->columnSpanFull(),
                                            ])
                                            ->visible(fn (Get $get) => $get('booking_type') === 'service'),

                                        // Package Selection (for package booking)
                                        Forms\Components\Section::make(__('booking::booking.fields.package'))
                                            ->schema([
                                                // Toggle between existing package or buy new
                                                Forms\Components\ToggleButtons::make('package_mode')
                                                    ->label('')
                                                    ->options([
                                                        'existing' => __('booking::booking.package_modes.use_existing'),
                                                        'new' => __('booking::booking.package_modes.buy_new'),
                                                    ])
                                                    ->icons([
                                                        'existing' => 'heroicon-o-folder-open',
                                                        'new' => 'heroicon-o-shopping-cart',
                                                    ])
                                                    ->default('existing')
                                                    ->inline()
                                                    ->live()
                                                    ->dehydrated()
                                                    ->columnSpanFull(),

                                                // Patient's Existing Packages - now shown in patient_info two-column layout
                                                // Keeping Hidden fields for the selected package
                                                // The visible package pills are rendered in patient_info placeholder

                                                // Hidden field to store selected subscription
                                                Forms\Components\Hidden::make('package_subscription_id')
                                                    ->dehydrated(),

                                                // Buy New Package - Dropdown
                                                Forms\Components\Select::make('new_package_id')
                                                    ->label(__('booking::booking.fields.select_package_to_buy'))
                                                    ->options(function () {
                                                        return Package::query()
                                                            ->where('is_active', true)
                                                            ->get()
                                                            ->mapWithKeys(fn (Package $pkg) => [
                                                                $pkg->id => "{$pkg->translated_name} - {$pkg->formatted_price} ({$pkg->total_sessions} " . __('booking::booking.labels.sessions') . ")"
                                                            ]);
                                                    })
                                                    ->searchable()
                                                    ->live()
                                                    ->dehydrated()
                                                    ->afterStateUpdated(fn ($state, $livewire) => $state ? $livewire->selectNewPackageForBooking($state) : null)
                                                    ->required(fn (Get $get) => $get('booking_type') === 'package' && $get('package_mode') === 'new')
                                                    ->visible(fn (Get $get) => $get('package_mode') === 'new')
                                                    ->columnSpanFull(),

                                                // Services from Selected Package - only show for NEW packages (existing packages services shown in patient_info)
                                                Forms\Components\ViewField::make('package_services_cards')
                                                    ->view('booking::components.package-services-grid')
                                                    ->viewData(fn (Get $get, $livewire) => [
                                                        'packageMode' => $get('package_mode') ?? 'existing',
                                                        'subscriptionId' => $get('package_subscription_id'),
                                                        'packageId' => $get('new_package_id'),
                                                        'selectedServiceId' => $get('package_service_id') ?? $get('new_package_service_id'),
                                                        'bookingItems' => $livewire->bookingItems ?? [],
                                                    ])
                                                    ->visible(fn (Get $get) => $get('package_mode') === 'new' && $get('new_package_id'))
                                                    ->columnSpanFull(),

                                                // Hidden fields to store selected service
                                                Forms\Components\Hidden::make('package_service_id'),
                                                Forms\Components\Hidden::make('new_package_service_id'),
                                            ])
                                            ->visible(fn (Get $get) => $get('booking_type') === 'package')
                                            ->dehydrated(),

                                        // Treatment Plan Selection (for treatment plan booking)
                                        Forms\Components\Fieldset::make(__('booking::booking.fields.treatment_plan'))
                                            ->schema([
                                                // Hidden field to store selected treatment plan (selection is done via pills in patient_info)
                                                Forms\Components\Hidden::make('treatment_plan_id'),

                                                Forms\Components\Placeholder::make('treatment_plan_progress')
                                                    ->label(__('booking::booking.fields.plan_progress'))
                                                    ->content(function (Get $get) {
                                                        $planId = $get('treatment_plan_id');
                                                        if (!$planId) {
                                                            return '-';
                                                        }
                                                        try {
                                                            $plan = TreatmentPlan::with('items')->find($planId);
                                                            if (!$plan) {
                                                                return '-';
                                                            }
                                                            return new HtmlString(
                                                                "<div class='text-sm'>" .
                                                                "<strong>{$plan->total_completed_sessions}</strong> of <strong>{$plan->total_recommended_sessions}</strong> sessions completed " .
                                                                "(<strong>{$plan->progress_percentage}%</strong>)" .
                                                                "</div>"
                                                            );
                                                        } catch (\Exception $e) {
                                                            return '-';
                                                        }
                                                    })
                                                    ->visible(fn (Get $get) => $get('treatment_plan_id')),

                                                // Treatment Plan Items - now shown in patient_info two-column layout
                                                // Service selection is done via pills in the right column of patient_info
                                                Forms\Components\Hidden::make('treatment_plan_item_id'),

                                                Forms\Components\Placeholder::make('treatment_plan_item_info')
                                                    ->label(__('booking::booking.fields.scheduling_preferences'))
                                                    ->content(function (Get $get) {
                                                        $itemId = $get('treatment_plan_item_id');
                                                        if (!$itemId) {
                                                            return '-';
                                                        }
                                                        try {
                                                            $item = TreatmentPlanItem::with(['preferredPractitioner'])->find($itemId);
                                                            if (!$item) {
                                                                return '-';
                                                            }
                                                            $info = [];
                                                            if ($item->session_interval_days) {
                                                                $info[] = "Interval: {$item->session_interval_days} days";
                                                            }
                                                            if ($item->preferredPractitioner) {
                                                                $info[] = "Preferred: {$item->preferredPractitioner->name}";
                                                            }
                                                            if ($item->preferred_time_slot) {
                                                                $info[] = "Time: {$item->time_slot_label}";
                                                            }
                                                            return empty($info) ? 'No preferences set' : implode(' | ', $info);
                                                        } catch (\Exception $e) {
                                                            return '-';
                                                        }
                                                    })
                                                    ->visible(fn (Get $get) => $get('treatment_plan_item_id')),

                                                Forms\Components\TextInput::make('treatment_plan_duration_override')
                                                    ->label(__('booking::booking.fields.duration_override'))
                                                    ->numeric()
                                                    ->suffix(__('booking::booking.minutes'))
                                                    ->helperText(__('booking::booking.fields.duration_override_help'))
                                                    ->visible(fn (Get $get) => $get('treatment_plan_item_id')),
                                            ])
                                            ->visible(fn (Get $get) => $get('booking_type') === 'treatment_plan'),
                                    ]),

                                // Schedule Section
                                Forms\Components\Section::make(__('booking::booking.sections.schedule'))
                                    ->description(__('booking::booking.sections.schedule_desc'))
                                    ->schema([
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\Select::make('branch_id')
                                                    ->label(__('booking::booking.fields.branch'))
                                                    ->options(function () {
                                                        return Branch::query()
                                                            ->active()
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->default(fn () => current_branch_id())
                                                    ->required()
                                                    ->live(),

                                                Forms\Components\DatePicker::make('date_from')
                                                    ->label(__('booking::booking.fields.date_from'))
                                                    ->native(false)
                                                    ->minDate(today())
                                                    ->maxDate(function (Get $get) {
                                                        $branchId = $get('branch_id');
                                                        if ($branchId) {
                                                            $evaluator = app(BookingRuleEvaluator::class)->forContext($branchId);
                                                            return today()->addDays($evaluator->getMaxAdvanceDays());
                                                        }
                                                        return today()->addDays(60);
                                                    })
                                                    ->default(today())
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                        $dateTo = $get('date_to');
                                                        if ($dateTo && Carbon::parse($state)->gt(Carbon::parse($dateTo))) {
                                                            $set('date_to', Carbon::parse($state)->addWeek()->format('Y-m-d'));
                                                        }
                                                    }),

                                                Forms\Components\DatePicker::make('date_to')
                                                    ->label(__('booking::booking.fields.date_to'))
                                                    ->native(false)
                                                    ->minDate(fn (Get $get) => $get('date_from') ? Carbon::parse($get('date_from')) : today())
                                                    ->maxDate(function (Get $get) {
                                                        $branchId = $get('branch_id');
                                                        if ($branchId) {
                                                            $evaluator = app(BookingRuleEvaluator::class)->forContext($branchId);
                                                            return today()->addDays($evaluator->getMaxAdvanceDays());
                                                        }
                                                        return today()->addDays(60);
                                                    })
                                                    ->default(today()->addWeek())
                                                    ->required()
                                                    ->live(),

                                                Forms\Components\Select::make('source')
                                                    ->label(__('booking::booking.fields.source'))
                                                    ->options(Appointment::SOURCES)
                                                    ->default(Appointment::SOURCE_PHONE)
                                                    ->required(),
                                            ]),

                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('generate_slots')
                                                ->label(__('booking::booking.actions.generate_slots'))
                                                ->icon('heroicon-o-magnifying-glass')
                                                ->action(fn () => $this->generateSlots())
                                                ->color('primary')
                                                ->size('lg'),

                                            Forms\Components\Actions\Action::make('find_next_available')
                                                ->label(__('booking::booking.actions.find_next'))
                                                ->icon('heroicon-o-forward')
                                                ->action(fn () => $this->findNextAvailable())
                                                ->color('gray'),
                                        ])->fullWidth(),

                                        // Slot Grid is now rendered directly in the page view for proper reactivity
                                    ]),

                                // Notes Section
                                Forms\Components\TextInput::make('notes')
                                    ->label(__('booking::booking.fields.notes'))
                                    ->maxLength(1000)
                                    ->placeholder(__('booking::booking.fields.notes'))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        // Booking Cart is now rendered directly in the page view
                    ]),
            ])
            ->statePath('data');
    }

    // Slot Generation

    public function generateSlots(): void
    {
        $data = $this->form->getState();
        $branchId = $data['branch_id'] ?? null;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;
        $bookingType = $data['booking_type'] ?? 'service';

        // Use hidden fields as fallback (they persist outside conditional sections)
        $packageMode = $data['package_mode'] ?? $data['_package_mode'] ?? null;
        $packageSubscriptionId = $data['package_subscription_id'] ?? $data['_package_subscription_id'] ?? null;
        $newPackageId = $data['new_package_id'] ?? $data['_new_package_id'] ?? null;

        \Log::warning('generateSlots called', [
            'booking_type' => $bookingType,
            'package_mode' => $packageMode,
            'package_subscription_id' => $packageSubscriptionId,
            'package_service_id' => $data['package_service_id'] ?? null,
            'new_package_id' => $newPackageId,
            'services_count' => count($data['services'] ?? []),
            'first_service_source_type' => $data['services'][0]['source_type'] ?? null,
        ]);

        if (!$branchId || !$dateFrom || !$dateTo) {
            Notification::make()
                ->title(__('booking::booking.validation.branch_date_required'))
                ->warning()
                ->send();
            return;
        }

        // Get service IDs based on booking type
        $serviceIds = [];
        $durations = [];
        $serviceSourceTypes = []; // Track source_type per service
        $treatmentPlanItemId = null;

        if ($bookingType === 'service') {
            $services = $data['services'] ?? [];
            foreach ($services as $service) {
                if (!empty($service['service_id'])) {
                    $serviceIds[] = $service['service_id'];
                    $durations[$service['service_id']] = $service['duration_override'] ?? null;
                    $serviceSourceTypes[$service['service_id']] = $service['source_type'] ?? null;
                }
            }
        } elseif ($bookingType === 'package') {
            $packageMode = $data['package_mode'] ?? 'existing';
            if ($packageMode === 'existing') {
                $packageServiceId = $data['package_service_id'] ?? null;
                if ($packageServiceId) {
                    $serviceIds[] = $packageServiceId;
                    $durations[$packageServiceId] = $data['package_duration_override'] ?? null;
                }
            } else {
                // New package mode
                $newPackageServiceId = $data['new_package_service_id'] ?? null;
                if ($newPackageServiceId) {
                    $serviceIds[] = $newPackageServiceId;
                    $durations[$newPackageServiceId] = $data['package_duration_override'] ?? null;
                }
            }
        } elseif ($bookingType === 'treatment_plan') {
            $treatmentPlanItemId = $data['treatment_plan_item_id'] ?? null;
            if ($treatmentPlanItemId) {
                $item = TreatmentPlanItem::find($treatmentPlanItemId);
                if ($item) {
                    $serviceIds[] = $item->service_id;
                    $durations[$item->service_id] = $data['treatment_plan_duration_override'] ?? null;
                }
            }
        }

        if (empty($serviceIds)) {
            Notification::make()
                ->title(__('booking::booking.validation.service_required'))
                ->warning()
                ->send();
            return;
        }

        $slotService = app(SlotGenerationService::class);
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $allSlots = [];

        // Iterate through each date in the range
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            foreach ($serviceIds as $serviceId) {
                $durationOverride = $durations[$serviceId] ?? null;
                $slots = $slotService->generateAvailableSlots(
                    $serviceId,
                    $branchId,
                    $currentDate,
                    $durationOverride ? (int) $durationOverride : null
                );

                foreach ($slots as $slot) {
                    $slot['service_id'] = $serviceId;
                    $slot['service_name'] = Service::find($serviceId)?->translated_name;

                    // Handle package info based on mode
                    // First check service-level package IDs, then fall back to form-level
                    $serviceSourceType = $serviceSourceTypes[$serviceId] ?? null;
                    $isPackageService = $serviceSourceType === 'package' || $serviceSourceType === 'treatment_plan';

                    // Get service-level package IDs
                    $serviceFromPackage = null;
                    $serviceNewPackageId = null;
                    foreach ($services as $service) {
                        if (($service['service_id'] ?? null) == $serviceId) {
                            $serviceFromPackage = $service['from_package'] ?? null;
                            $serviceNewPackageId = $service['new_package_id'] ?? null;
                            break;
                        }
                    }

                    // Use service-level package IDs if available
                    if ($serviceFromPackage) {
                        $slot['from_package'] = $serviceFromPackage;
                        $slot['new_package_id'] = null;
                    } elseif ($serviceNewPackageId) {
                        $slot['from_package'] = null;
                        $slot['new_package_id'] = $serviceNewPackageId;
                    } elseif ($bookingType === 'package' || !empty($packageSubscriptionId) || $isPackageService) {
                        if ($packageMode === 'existing' || !empty($packageSubscriptionId)) {
                            $slot['from_package'] = $packageSubscriptionId;
                            $slot['new_package_id'] = null;
                        } else {
                            $slot['from_package'] = null;
                            $slot['new_package_id'] = $newPackageId;
                        }
                    } elseif (!empty($newPackageId)) {
                        $slot['from_package'] = null;
                        $slot['new_package_id'] = $newPackageId;
                    } else {
                        $slot['from_package'] = null;
                        $slot['new_package_id'] = null;
                    }

                    $slot['treatment_plan_item_id'] = $bookingType === 'treatment_plan' ? $treatmentPlanItemId : null;
                    $allSlots[] = $slot;
                }
            }
            $currentDate->addDay();
        }

        $this->availableSlots = $allSlots;

        if (empty($this->availableSlots)) {
            Notification::make()
                ->title(__('booking::booking.messages.no_slots_available'))
                ->body(__('booking::booking.messages.try_different_date'))
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title(__('booking::booking.messages.slots_found'))
                ->body(__('booking::booking.messages.slots_count', ['count' => count($allSlots)]))
                ->success()
                ->send();
        }
    }

    public function findNextAvailable(): void
    {
        $data = $this->form->getState();
        $branchId = $data['branch_id'] ?? null;
        $bookingType = $data['booking_type'] ?? 'service';

        $serviceId = null;
        if ($bookingType === 'service') {
            $services = $data['services'] ?? [];
            $serviceId = $services[0]['service_id'] ?? null;
        } elseif ($bookingType === 'package') {
            $packageMode = $data['package_mode'] ?? 'existing';
            if ($packageMode === 'existing') {
                $serviceId = $data['package_service_id'] ?? null;
            } else {
                $serviceId = $data['new_package_service_id'] ?? null;
            }
        } elseif ($bookingType === 'treatment_plan') {
            $treatmentPlanItemId = $data['treatment_plan_item_id'] ?? null;
            if ($treatmentPlanItemId) {
                $item = TreatmentPlanItem::find($treatmentPlanItemId);
                $serviceId = $item?->service_id;
            }
        }

        if (!$serviceId || !$branchId) {
            Notification::make()
                ->title(__('booking::booking.validation.service_branch_required'))
                ->warning()
                ->send();
            return;
        }

        $slotService = app(SlotGenerationService::class);
        $result = $slotService->findNextAvailableSlot(
            $serviceId,
            $branchId,
            Carbon::parse($data['date_from'] ?? today()),
            30
        );

        if ($result) {
            $foundDate = Carbon::parse($result['date']);
            $this->data['date_from'] = $result['date'];
            $this->data['date_to'] = $foundDate->copy()->addWeek()->format('Y-m-d');
            $this->generateSlots();

            Notification::make()
                ->title(__('booking::booking.messages.next_available_found'))
                ->body(__('booking::booking.messages.date_updated', ['date' => $result['date']]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('booking::booking.messages.no_availability'))
                ->danger()
                ->send();
        }
    }

    /**
     * Generate slots for a specific service (called from service row button)
     */
    public function generateSlotsForService(string $serviceId, ?int $durationOverride = null): void
    {
        $data = $this->form->getState();
        $branchId = $data['branch_id'] ?? null;
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;
        $bookingType = $data['booking_type'] ?? 'service';

        // Use hidden fields as fallback (they persist outside conditional sections)
        $packageMode = $data['package_mode'] ?? $data['_package_mode'] ?? null;
        $packageSubscriptionId = $data['package_subscription_id'] ?? $data['_package_subscription_id'] ?? null;
        $newPackageId = $data['new_package_id'] ?? $data['_new_package_id'] ?? null;

        \Log::warning('generateSlotsForService called', [
            'service_id' => $serviceId,
            'booking_type' => $bookingType,
            'package_mode' => $packageMode,
            'package_subscription_id' => $packageSubscriptionId,
            'new_package_id' => $newPackageId,
            'services_count' => count($data['services'] ?? []),
            'services_raw' => $data['services'] ?? [],
        ]);

        if (!$branchId || !$dateFrom || !$dateTo) {
            Notification::make()
                ->title(__('booking::booking.validation.branch_date_required'))
                ->warning()
                ->send();
            return;
        }

        $slotService = app(SlotGenerationService::class);
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $allSlots = [];

        // Get package/treatment plan context if applicable
        $fromPackage = null;
        $slotNewPackageId = null;  // Renamed to avoid shadowing
        $treatmentPlanItemId = null;

        // Check if the service is from a package (via services array)
        $services = $data['services'] ?? [];
        $serviceSourceType = null;
        $serviceFromPackage = null;
        $serviceNewPackageId = null;
        foreach ($services as $service) {
            if (($service['service_id'] ?? null) == $serviceId) {
                $serviceSourceType = $service['source_type'] ?? null;
                $serviceFromPackage = $service['from_package'] ?? null;
                $serviceNewPackageId = $service['new_package_id'] ?? null;
                break;
            }
        }
        $isPackageService = $serviceSourceType === 'package';

        \Log::warning('generateSlotsForService - service found', [
            'serviceSourceType' => $serviceSourceType,
            'serviceFromPackage' => $serviceFromPackage,
            'serviceNewPackageId' => $serviceNewPackageId,
            'isPackageService' => $isPackageService,
        ]);

        // Use service-level package IDs if available, otherwise fall back to form-level
        if ($serviceFromPackage) {
            $fromPackage = $serviceFromPackage;
            $slotNewPackageId = null;
        } elseif ($serviceNewPackageId) {
            $fromPackage = null;
            $slotNewPackageId = $serviceNewPackageId;
        } elseif ($bookingType === 'package' || $isPackageService || !empty($packageSubscriptionId) || !empty($newPackageId)) {
            if ($packageMode === 'existing' || !empty($packageSubscriptionId)) {
                $fromPackage = $packageSubscriptionId;
            } else {
                $fromPackage = null;
                $slotNewPackageId = $newPackageId;  // Use form-level value
            }
        } elseif ($bookingType === 'treatment_plan') {
            $treatmentPlanItemId = $data['treatment_plan_item_id'] ?? null;
        }

        \Log::warning('generateSlotsForService - package IDs resolved', [
            'fromPackage' => $fromPackage,
            'slotNewPackageId' => $slotNewPackageId,
        ]);

        // Iterate through each date in the range
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $slots = $slotService->generateAvailableSlots(
                $serviceId,
                $branchId,
                $currentDate,
                $durationOverride ? (int) $durationOverride : null
            );

            foreach ($slots as $slot) {
                $slot['service_id'] = $serviceId;
                $slot['service_name'] = Service::find($serviceId)?->translated_name;
                $slot['from_package'] = $fromPackage;
                $slot['new_package_id'] = $slotNewPackageId;
                $slot['treatment_plan_item_id'] = $treatmentPlanItemId;
                $allSlots[] = $slot;
            }

            $currentDate->addDay();
        }

        $this->availableSlots = $allSlots;

        // Store the current service being selected
        $this->dispatch('slots-generated', serviceId: $serviceId);

        if (count($allSlots) > 0) {
            Notification::make()
                ->title(__('booking::booking.messages.slots_found'))
                ->body(__('booking::booking.messages.slots_count', ['count' => count($allSlots)]))
                ->success()
                ->duration(2000)
                ->send();
        } else {
            Notification::make()
                ->title(__('booking::booking.messages.no_slots_found'))
                ->warning()
                ->send();
        }
    }

    public function selectSlot(array $slot): void
    {
        \Log::warning('selectSlot called', [
            'slot_service_id' => $slot['service_id'] ?? null,
            'slot_from_package' => $slot['from_package'] ?? null,
            'slot_new_package_id' => $slot['new_package_id'] ?? null,
        ]);

        $serviceId = $slot['service_id'] ?? null;
        $slotKey = $slot['date'] . '_' . $slot['start_time'] . '_' . $serviceId;
        $practitionerId = $slot['practitioner_id'] ?? $slot['available_practitioners'][0]['id'] ?? null;
        $notificationTitle = null;
        $notificationType = 'success';

        // Find if this service already has a booking
        $existingServiceIndex = null;
        $existingSlotKey = null;
        foreach ($this->bookingItems as $index => $item) {
            if ($item['service_id'] === $serviceId) {
                $existingServiceIndex = $index;
                $existingSlotKey = $item['date'] . '_' . $item['start_time'] . '_' . $item['service_id'];
                break;
            }
        }

        // Case 1: Same slot + same practitioner = toggle off (deselect)
        if ($existingServiceIndex !== null && $existingSlotKey === $slotKey) {
            $existingPractitionerId = $this->bookingItems[$existingServiceIndex]['practitioner_id'];

            if ($existingPractitionerId === $practitionerId) {
                // Toggle off - remove the booking
                unset($this->bookingItems[$existingServiceIndex]);
                $this->bookingItems = array_values($this->bookingItems);
                $notificationTitle = __('booking::booking.messages.slot_removed');
                $notificationType = 'info';
            } else {
                // Case 2: Same slot + different practitioner = change practitioner
                $this->bookingItems[$existingServiceIndex]['practitioner_id'] = $practitionerId;
                $this->bookingItems[$existingServiceIndex]['practitioner_name'] = $slot['practitioner_name'] ?? null;
                $notificationTitle = __('booking::booking.messages.practitioner_changed');
                $notificationType = 'info';
            }
        } else {
            // Case 3: Different slot for same service = replace the slot
            // Case 4: No existing booking for this service = add new
            $newItem = [
                'service_id' => $serviceId,
                'service_name' => $slot['service_name'] ?? Service::find($serviceId)?->translated_name,
                'date' => $slot['date'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'duration' => $slot['duration'],
                'practitioner_id' => $practitionerId,
                'practitioner_name' => $slot['practitioner_name'] ?? null,
                'room_id' => $slot['room_id'] ?? null,
                'room_name' => $slot['room_name'] ?? null,
                'equipment_id' => $slot['equipment_id'] ?? null,
                'equipment_name' => $slot['equipment_name'] ?? null,
                'from_package' => $slot['from_package'] ?? null,
                'new_package_id' => $slot['new_package_id'] ?? null,
                'treatment_plan_item_id' => $slot['treatment_plan_item_id'] ?? null,
            ];

            if ($existingServiceIndex !== null) {
                // Replace existing slot for this service
                $this->bookingItems[$existingServiceIndex] = $newItem;
                $notificationTitle = __('booking::booking.messages.slot_changed');
            } else {
                // Add new booking
                $this->bookingItems[] = $newItem;
                $notificationTitle = __('booking::booking.messages.slot_added');
            }
        }

        // Send notification
        if ($notificationTitle) {
            $notification = Notification::make()
                ->title($notificationTitle)
                ->duration(1500);

            if ($notificationType === 'info') {
                $notification->info();
            } else {
                $notification->success();
            }

            $notification->send();
        }
    }

    /**
     * Get selected slot keys for tracking in the UI.
     */
    public function getSelectedSlotKeys(): array
    {
        $keys = [];
        foreach ($this->bookingItems as $item) {
            $key = $item['date'] . '_' . $item['start_time'] . '_' . ($item['service_id'] ?? '');
            $keys[$key] = [
                // Cast to string for consistent comparison in views
                'practitioner_id' => (string) ($item['practitioner_id'] ?? ''),
                'practitioner_name' => $item['practitioner_name'] ?? '',
            ];
        }
        return $keys;
    }

    public function removeBookingItem(int $index): void
    {
        if (isset($this->bookingItems[$index])) {
            unset($this->bookingItems[$index]);
            $this->bookingItems = array_values($this->bookingItems);
        }
    }

    public function clearCart(): void
    {
        $this->bookingItems = [];
    }

    /**
     * Select a package subscription for booking (called from patient info card)
     * Loads all available services from the package into the cart
     */
    public function selectPackageForBooking(string $subscriptionId): void
    {
        // Clear any previous slot selections
        $this->availableSlots = [];
        $this->bookingItems = [];

        // Get current form state
        $this->data = $this->form->getState();

        try {
            $subscription = PackageSubscription::with([
                'package.items.service',
            ])->find($subscriptionId);

            if ($subscription && $subscription->isActive()) {
                $services = [];

                foreach ($subscription->package->items as $item) {
                    if (!$item->service) {
                        continue;
                    }

                    // Check remaining sessions for this service
                    $remaining = $subscription->getSessionsRemainingByService($item->service_id);
                    if ($remaining <= 0) {
                        continue;
                    }

                    // Check for existing booked (but not completed) appointments
                    $scheduledAppointment = Appointment::where('package_subscription_id', $subscriptionId)
                        ->where('service_id', $item->service_id)
                        ->where('is_package_session', true)
                        ->whereIn('status', [
                            Appointment::STATUS_SCHEDULED,
                            Appointment::STATUS_CONFIRMED,
                        ])
                        ->orderBy('date')
                        ->first();

                    // Convert price from piastres to EGP for display
                    $services[] = [
                        'service_id' => $item->service_id,
                        'duration_override' => $item->service->duration_minutes,
                        'price_minor' => ($item->unit_price_minor ?? 0) / 100, // EGP for display
                        'discount_minor' => 0,
                        'max_discount_percent' => 0, // No discount allowed for package
                        'source_type' => 'package',
                        'source_item_id' => $item->id,
                        'package_sessions' => $item->quantity,
                        'package_sessions_remaining' => $remaining,
                        'package_consumption_type' => $item->consumption_type,
                        'package_pulses_per_session' => $item->pulses_per_session,
                        'existing_appointment_id' => $scheduledAppointment?->id,
                        'existing_appointment_date' => $scheduledAppointment?->date?->format('Y-m-d'),
                        'existing_appointment_time' => $scheduledAppointment?->start_time,
                        // Store package subscription ID directly in service item
                        'from_package' => $subscriptionId,
                        'new_package_id' => null,
                    ];
                }

                // Update form data
                $this->data['booking_type'] = 'service'; // Use service mode for cart display
                $this->data['package_mode'] = 'existing';
                $this->data['package_subscription_id'] = $subscriptionId;
                // Also set hidden fields that persist outside conditional sections
                $this->data['_package_mode'] = 'existing';
                $this->data['_package_subscription_id'] = $subscriptionId;
                $this->data['services'] = !empty($services) ? $services : [['service_id' => null, 'duration_override' => null, 'price_minor' => null]];
                // Clear treatment plan selection when selecting package
                $this->data['treatment_plan_id'] = null;
                $this->data['treatment_plan_item_id'] = null;

                // Refresh the form with new data
                $this->form->fill($this->data);
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            \Illuminate\Support\Facades\Log::warning('Error loading package services', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }

        Notification::make()
            ->title(__('booking::booking.messages.package_selected'))
            ->body(__('booking::booking.messages.services_loaded_to_cart'))
            ->success()
            ->duration(2000)
            ->send();
    }

    /**
     * Select a new package for booking (called when selecting package to buy)
     * Loads all package services into the cart with package pricing
     */
    public function selectNewPackageForBooking(string $packageId): void
    {
        // Clear any previous slot selections
        $this->availableSlots = [];
        $this->bookingItems = [];

        // Get current form state
        $this->data = $this->form->getState();

        try {
            $package = Package::with(['items.service'])->find($packageId);

            if ($package) {
                $services = [];

                foreach ($package->items as $item) {
                    if (!$item->service) {
                        continue;
                    }

                    // Convert price from piastres to EGP for display
                    $services[] = [
                        'service_id' => $item->service_id,
                        'duration_override' => $item->service->duration_minutes,
                        'price_minor' => ($item->unit_price_minor ?? 0) / 100, // EGP for display
                        'discount_minor' => 0,
                        'max_discount_percent' => 0, // No discount allowed for package
                        'source_type' => 'package',
                        'source_item_id' => $item->id,
                        'package_sessions' => $item->quantity,
                        'package_consumption_type' => $item->consumption_type,
                        'package_pulses_per_session' => $item->pulses_per_session,
                        'existing_appointment_id' => null,
                        'existing_appointment_date' => null,
                        'existing_appointment_time' => null,
                        // Store package ID directly in service item
                        'from_package' => null,
                        'new_package_id' => $packageId,
                    ];
                }

                // Update form data - use service mode for cart display
                $this->data['booking_type'] = 'service';
                $this->data['package_mode'] = 'new';
                $this->data['new_package_id'] = $packageId;
                // Also set hidden fields that persist outside conditional sections
                $this->data['_package_mode'] = 'new';
                $this->data['_new_package_id'] = $packageId;
                $this->data['services'] = !empty($services) ? $services : [['service_id' => null, 'duration_override' => null, 'price_minor' => null]];
                // Clear treatment plan selection when selecting new package
                $this->data['treatment_plan_id'] = null;
                $this->data['treatment_plan_item_id'] = null;

                // Refresh the form with new data
                $this->form->fill($this->data);

                Notification::make()
                    ->title(__('booking::booking.messages.package_selected'))
                    ->body(__('booking::booking.messages.services_loaded_to_cart'))
                    ->success()
                    ->duration(2000)
                    ->send();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error loading new package services', [
                'package_id' => $packageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Select a treatment plan for booking (called from patient info card)
     * Loads all bookable services into the cart with pricing and existing booking info
     */
    public function selectTreatmentPlanForBooking(string $planId): void
    {
        // Clear any previous slot selections
        $this->availableSlots = [];
        $this->bookingItems = [];

        // Get current form state
        $this->data = $this->form->getState();

        try {
            $plan = TreatmentPlan::with([
                'items.service',
                'items.planAppointments.appointment',
                'packageSubscription.package.items',
            ])->find($planId);

            if ($plan) {
                $services = [];
                $firstBookableItem = null;
                $isFromPackage = $plan->hasPackageSubscription();
                $packageItems = [];

                // If from package, index package items by service_id for lookup
                if ($isFromPackage && $plan->packageSubscription) {
                    foreach ($plan->packageSubscription->package->items as $pkgItem) {
                        $packageItems[$pkgItem->service_id] = $pkgItem;
                    }
                }

                foreach ($plan->items as $item) {
                    // Skip items without service or that are completed/cancelled
                    if (!$item->service || $item->isCompleted() || $item->isCancelled()) {
                        continue;
                    }

                    if (!$firstBookableItem) {
                        $firstBookableItem = $item;
                    }

                    // Check for existing scheduled (but not completed) appointment
                    $scheduledAppointment = $item->planAppointments()
                        ->whereHas('appointment', function ($query) {
                            $query->whereIn('status', [
                                Appointment::STATUS_SCHEDULED,
                                Appointment::STATUS_CONFIRMED,
                            ]);
                        })
                        ->with('appointment')
                        ->first();

                    // Get remaining sessions if from package
                    $remaining = null;
                    if ($isFromPackage && $plan->packageSubscription) {
                        $remaining = $plan->packageSubscription->getSessionsRemainingByService($item->service_id);
                    }

                    // Get package item info if from package
                    $pkgItem = $packageItems[$item->service_id] ?? null;

                    // Determine price: from existing appointment > package item > treatment plan item > service default
                    // DB stores piastres, convert to EGP for form display
                    $priceInEgp = null;
                    $durationMinutes = $item->service->duration_minutes;
                    if ($scheduledAppointment?->appointment) {
                        // Use price and duration from existing appointment
                        $priceInEgp = ($scheduledAppointment->appointment->price_minor ?? 0) / 100;
                        $durationMinutes = $scheduledAppointment->appointment->duration_minutes ?? $durationMinutes;
                    } elseif ($pkgItem) {
                        $priceInEgp = ($pkgItem->unit_price_minor ?? 0) / 100;
                    } else {
                        $priceInEgp = (($item->unit_price_minor ?? $item->service->base_price_minor) ?? 0) / 100;
                    }

                    $services[] = [
                        'service_id' => $item->service_id,
                        'duration_override' => $durationMinutes,
                        'price_minor' => $priceInEgp, // EGP for display (will be converted back to piastres on save)
                        'discount_minor' => 0,
                        'max_discount_percent' => $isFromPackage ? 0 : ($item->service->max_discount_percent ?? 100),
                        // If from package, treat as package source
                        'source_type' => $isFromPackage ? 'package' : 'treatment_plan',
                        'source_item_id' => $item->id,
                        // Package subscription tracking
                        'from_package' => $isFromPackage ? $plan->package_subscription_id : null,
                        // Package session info
                        'package_sessions' => $pkgItem?->quantity,
                        'package_sessions_remaining' => $remaining,
                        'package_consumption_type' => $pkgItem?->consumption_type,
                        'package_pulses_per_session' => $pkgItem?->pulses_per_session,
                        'existing_appointment_id' => $scheduledAppointment?->appointment_id,
                        'existing_appointment_date' => $scheduledAppointment?->appointment?->date?->format('Y-m-d'),
                        'existing_appointment_time' => $scheduledAppointment?->appointment?->start_time,
                    ];
                }

                // Update form data
                $this->data['booking_type'] = 'service'; // Use service mode for cart display
                $this->data['treatment_plan_id'] = $planId;
                $this->data['services'] = !empty($services) ? $services : [['service_id' => null, 'duration_override' => null, 'price_minor' => null]];

                // Clear/set package subscription based on treatment plan source
                if ($isFromPackage && $plan->packageSubscription) {
                    $this->data['package_subscription_id'] = $plan->package_subscription_id;
                } else {
                    // Clear package subscription if treatment plan is not from package
                    $this->data['package_subscription_id'] = null;
                }

                if ($firstBookableItem) {
                    $this->data['treatment_plan_item_id'] = $firstBookableItem->id;

                    // Set suggested dates based on item's next suggested date
                    if ($firstBookableItem->next_suggested_date) {
                        $this->data['date_from'] = $firstBookableItem->next_suggested_date->format('Y-m-d');
                        $this->data['date_to'] = $firstBookableItem->next_suggested_date->copy()->addWeeks(2)->format('Y-m-d');
                    }
                }

                // Refresh the form with new data
                $this->form->fill($this->data);
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            \Illuminate\Support\Facades\Log::warning('Error loading treatment plan services', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
        }

        Notification::make()
            ->title(__('booking::booking.messages.treatment_plan_selected'))
            ->body(__('booking::booking.messages.services_loaded_to_cart'))
            ->success()
            ->duration(2000)
            ->send();
    }

    /**
     * Select a treatment plan item/service (called from treatment plan items pills)
     */
    public function selectTreatmentPlanItem(string $itemId): void
    {
        $this->data['treatment_plan_item_id'] = $itemId;

        // Clear any previous slot selections
        $this->availableSlots = [];

        // Set suggested dates based on item's next suggested date
        try {
            $item = TreatmentPlanItem::find($itemId);
            if ($item && $item->next_suggested_date) {
                $this->data['date_from'] = $item->next_suggested_date->format('Y-m-d');
                $this->data['date_to'] = $item->next_suggested_date->addWeeks(2)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // Silently fail if treatment plans not fully set up
        }
    }

    /**
     * Sync booking items with current services list
     * Removes booking items for services that no longer exist in the form
     */
    public function syncBookingItemsWithServices(): void
    {
        $data = $this->form->getState();
        $services = $data['services'] ?? [];

        // Get active service IDs from form
        $activeServiceIds = collect($services)
            ->pluck('service_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->toArray();

        // Remove booking items for services no longer in the list
        $this->bookingItems = array_values(array_filter(
            $this->bookingItems,
            fn ($item) => in_array((string) ($item['service_id'] ?? ''), $activeServiceIds)
        ));
    }

    // Booking Creation

    public function createBookings(): void
    {
        $data = $this->form->getState();

        if (empty($data['patient_id'])) {
            Notification::make()
                ->title(__('booking::booking.validation.patient_required'))
                ->danger()
                ->send();
            return;
        }

        if (empty($this->bookingItems)) {
            Notification::make()
                ->title(__('booking::booking.validation.slot_required'))
                ->danger()
                ->send();
            return;
        }

        $createdAppointments = [];
        $newPackageSubscriptions = []; // Track newly created subscriptions

        try {
            // Log search path for debugging
            $searchPath = DB::select('SHOW search_path')[0]->search_path ?? 'unknown';
            \Log::warning('createBookings: Starting', [
                'search_path' => $searchPath,
                'patient_id' => $data['patient_id'] ?? null,
            ]);

            // First, create any new package subscriptions needed
            foreach ($this->bookingItems as $item) {
                if (!empty($item['new_package_id']) && !isset($newPackageSubscriptions[$item['new_package_id']])) {
                    $package = Package::find($item['new_package_id']);
                    if ($package) {
                        \Log::warning('Creating package subscription', [
                            'patient_id' => $data['patient_id'],
                            'package_id' => $package->id,
                            'search_path' => $searchPath,
                        ]);

                        // Load items to calculate effective price
                        $package->load('items');
                        $priceMinor = $package->effective_price_minor;

                        $subscription = PackageSubscription::create([
                            'patient_id' => $data['patient_id'],
                            'package_id' => $package->id,
                            'branch_id' => $data['branch_id'],
                            'package_price_minor' => $priceMinor,
                            'deposit_paid_minor' => 0,
                            'balance_remaining_minor' => $priceMinor,
                            'recognized_revenue_minor' => 0,
                            'unrecognized_revenue_minor' => $priceMinor,
                            'status' => PackageSubscription::STATUS_ACTIVE,
                            'activation_rule' => PackageSubscription::ACTIVATION_IMMEDIATE,
                            'purchased_at' => now(),
                            'expires_at' => now()->addDays($package->validity_days ?? 365),
                            'created_by_user_id' => auth()->id(),
                        ]);
                        $newPackageSubscriptions[$item['new_package_id']] = $subscription->id;
                    }
                }
            }

            foreach ($this->bookingItems as $index => $item) {
                \Log::warning('Processing booking item', [
                    'index' => $index,
                    'service_id' => $item['service_id'] ?? null,
                    'from_package' => $item['from_package'] ?? null,
                    'new_package_id' => $item['new_package_id'] ?? null,
                    'treatment_plan_item_id' => $item['treatment_plan_item_id'] ?? null,
                ]);

                $service = Service::find($item['service_id']);

                // Determine if this is a package session
                $isPackageSession = !empty($item['from_package']) || !empty($item['new_package_id']);
                $packageSubscriptionId = $item['from_package'] ?? ($newPackageSubscriptions[$item['new_package_id']] ?? null);

                // Get discount from form's services array (match by service_id)
                $discountMinor = 0;
                if (!empty($data['services'])) {
                    foreach ($data['services'] as $formService) {
                        if (($formService['service_id'] ?? null) == $item['service_id']) {
                            $discountMinor = (int) ($formService['discount_minor'] ?? 0);
                            break;
                        }
                    }
                }

                $appointment = Appointment::create([
                    'patient_id' => $data['patient_id'],
                    'service_id' => $item['service_id'],
                    'branch_id' => $data['branch_id'],
                    'practitioner_id' => $item['practitioner_id'],
                    'room_id' => $item['room_id'],
                    'equipment_id' => $item['equipment_id'],
                    'date' => $item['date'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'duration_minutes' => $item['duration'],
                    // Package sessions have price 0 since they're prepaid
                    'price_minor' => $isPackageSession ? 0 : ($service?->base_price_minor ?? 0),
                    // Discount (only for non-package services)
                    'discount_minor' => $isPackageSession ? 0 : $discountMinor,
                    'discount_type' => Appointment::DISCOUNT_FIXED,
                    'status' => Appointment::STATUS_SCHEDULED,
                    'source' => $data['source'] ?? Appointment::SOURCE_PHONE,
                    'notes' => $data['notes'] ?? null,
                    // Package fields
                    'package_subscription_id' => $packageSubscriptionId,
                    'is_package_session' => $isPackageSession,
                    // Reschedule tracking
                    'rescheduled_from_id' => $this->rescheduleAppointmentId,
                ]);

                $createdAppointments[] = $appointment;

                // Handle package booking - create/link treatment plan (usage recorded on session completion)
                if (!empty($item['from_package'])) {
                    \Log::info('Package booking: from_package detected', [
                        'from_package' => $item['from_package'],
                        'service_id' => $item['service_id'],
                        'appointment_id' => $appointment->id,
                    ]);

                    // Note: PackageSessionUsage is created when appointment completes
                    // via CreateInvoiceOnAppointmentComplete listener (triggers revenue recognition)

                    // Create or find treatment plan for this package subscription
                    $subscription = PackageSubscription::find($item['from_package']);
                    if ($subscription) {
                        \Log::warning('Package booking: Creating treatment plan', [
                            'subscription_id' => $subscription->id,
                            'patient_id' => $subscription->patient_id,
                        ]);

                        try {
                            $treatmentPlanService = app(TreatmentPlanService::class);
                            $plan = TreatmentPlan::where('package_subscription_id', $subscription->id)->first();

                            \Log::warning('Package booking: Existing plan check', [
                                'existing_plan' => $plan ? $plan->id : null,
                            ]);

                            if (!$plan) {
                                $plan = $treatmentPlanService->createFromPackageSubscription($subscription, $data['branch_id']);
                                \Log::warning('Package booking: Created new plan', [
                                    'plan_id' => $plan->id,
                                    'plan_code' => $plan->code ?? null,
                                ]);
                            }

                            // Link appointment to treatment plan item
                            if ($plan) {
                                $planItem = $plan->items()->where('service_id', $item['service_id'])->first();
                                \Log::warning('Package booking: Found plan item', [
                                    'plan_item_id' => $planItem ? $planItem->id : null,
                                    'service_id' => $item['service_id'],
                                ]);

                                if ($planItem && !TreatmentPlanAppointment::where('appointment_id', $appointment->id)->exists()) {
                                    TreatmentPlanAppointment::create([
                                        'tenant_id' => $appointment->tenant_id,
                                        'treatment_plan_item_id' => $planItem->id,
                                        'appointment_id' => $appointment->id,
                                        'session_number' => $planItem->next_session_number,
                                        'status' => $appointment->status,
                                    ]);
                                    \Log::warning('Package booking: Created treatment plan appointment link');
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::error('Package booking: Treatment plan creation failed', [
                                'error' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);
                        }
                    } else {
                        \Log::warning('Package booking: Subscription not found', [
                            'from_package' => $item['from_package'],
                        ]);
                    }
                }

                // Handle new package booking - create treatment plan (usage recorded on session completion)
                if (!empty($item['new_package_id']) && isset($newPackageSubscriptions[$item['new_package_id']])) {
                    \Log::info('New package booking: new_package_id detected', [
                        'new_package_id' => $item['new_package_id'],
                        'subscription_id' => $newPackageSubscriptions[$item['new_package_id']],
                    ]);

                    $subscriptionId = $newPackageSubscriptions[$item['new_package_id']];

                    // Note: PackageSessionUsage is created when appointment completes
                    // via CreateInvoiceOnAppointmentComplete listener (triggers revenue recognition)

                    // Create treatment plan for new package subscription
                    $subscription = PackageSubscription::find($subscriptionId);
                    if ($subscription) {
                        try {
                            \Log::warning('New package booking: Creating treatment plan', [
                                'subscription_id' => $subscription->id,
                            ]);

                            $treatmentPlanService = app(TreatmentPlanService::class);
                            $plan = TreatmentPlan::where('package_subscription_id', $subscription->id)->first();

                            if (!$plan) {
                                $plan = $treatmentPlanService->createFromPackageSubscription($subscription, $data['branch_id']);
                                \Log::warning('New package booking: Created plan', [
                                    'plan_id' => $plan->id,
                                ]);
                            }

                            // Link appointment to treatment plan item
                            if ($plan) {
                                $planItem = $plan->items()->where('service_id', $item['service_id'])->first();
                                if ($planItem && !TreatmentPlanAppointment::where('appointment_id', $appointment->id)->exists()) {
                                    TreatmentPlanAppointment::create([
                                        'tenant_id' => $appointment->tenant_id,
                                        'treatment_plan_item_id' => $planItem->id,
                                        'appointment_id' => $appointment->id,
                                        'session_number' => $planItem->next_session_number,
                                        'status' => $appointment->status,
                                    ]);
                                    \Log::warning('New package booking: Linked appointment to plan');
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::error('New package booking: Treatment plan creation failed', [
                                'error' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);
                        }
                    }
                }

                // Link to treatment plan if from treatment plan
                if (!empty($item['treatment_plan_item_id'])) {
                    $planItem = TreatmentPlanItem::find($item['treatment_plan_item_id']);
                    if ($planItem) {
                        TreatmentPlanAppointment::create([
                            'tenant_id' => $appointment->tenant_id,
                            'treatment_plan_item_id' => $planItem->id,
                            'appointment_id' => $appointment->id,
                            'session_number' => $planItem->next_session_number,
                            'status' => $appointment->status,
                        ]);
                    }
                }
            }

            // Auto-create treatment plans for direct service bookings
            // (Package treatment plans are created inline above when recording package usage)
            $bookingType = $data['booking_type'] ?? 'service';

            if ($bookingType === 'service' && !empty($createdAppointments)) {
                $treatmentPlanService = app(TreatmentPlanService::class);
                // Direct service booking: Create treatment plan from booked appointments
                $appointmentsForNewPlan = array_filter($createdAppointments, function ($appt) {
                    return !TreatmentPlanAppointment::where('appointment_id', $appt->id)->exists();
                });

                if (!empty($appointmentsForNewPlan)) {
                    $treatmentPlanService->createFromServiceBooking(
                        $data['patient_id'],
                        $data['branch_id'],
                        $appointmentsForNewPlan
                    );
                }
            }
            // Package treatment plans are created inline when recording package usage above

            $count = count($createdAppointments);

            // If this is a reschedule, cancel the original appointment
            if ($this->rescheduleAppointment) {
                $this->rescheduleAppointment->update([
                    'status' => Appointment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancellation_reason' => __('booking::booking.messages.rescheduled_to_new'),
                ]);

                Notification::make()
                    ->title(__('booking::booking.messages.appointment_rescheduled'))
                    ->body(__('booking::booking.messages.rescheduled_success'))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title(__('booking::booking.messages.booking_created'))
                    ->body(__('booking::booking.messages.appointments_created', ['count' => $count]))
                    ->success()
                    ->send();
            }

            $this->redirect(route('filament.tenant.resources.appointments.index'));

        } catch (\Exception $e) {
            \Log::error('Booking creation failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title(__('booking::booking.messages.booking_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
