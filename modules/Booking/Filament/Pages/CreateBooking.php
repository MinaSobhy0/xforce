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

    public static function getNavigationLabel(): string
    {
        return __('booking::booking.navigation.create_booking');
    }

    public function getTitle(): string
    {
        return __('booking::booking.title.create_booking');
    }

    public function getHeading(): string
    {
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

        // Handle patient from query
        if ($patientIdFromQuery) {
            $formData['patient_id'] = $patientIdFromQuery;
        }

        // Handle package subscription from query
        if ($packageSubscriptionIdFromQuery) {
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

        // Handle treatment plan booking
        if ($bookingType === 'treatment_plan' && $treatmentPlanIdFromQuery) {
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
                                                            $isSelected = $currentBookingType === 'treatment_plan' && $selectedPlanId === $plan->id;
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
                                                    } elseif ($currentBookingType === 'treatment_plan' && $selectedPlanId) {
                                                        // Show selected treatment plan items
                                                        $selectedPlan = $activePlans->firstWhere('id', $selectedPlanId);
                                                        if ($selectedPlan) {
                                                            $html .= '<div class="flex items-center gap-2 mb-2">';
                                                            $html .= '<span class="font-semibold text-sm text-gray-900 dark:text-white">' . __('booking::booking.labels.select_services_to_book') . '</span>';
                                                            $html .= '</div>';
                                                            $html .= '<div class="flex flex-wrap gap-1.5">';

                                                            $selectedItemId = $this->data['treatment_plan_item_id'] ?? null;
                                                            foreach ($selectedPlan->items as $item) {
                                                                if ($item->canBook()) {
                                                                    $isItemSelected = $selectedItemId === $item->id;
                                                                    $nextDate = $item->next_suggested_date ? $item->next_suggested_date->format('M d') : '-';
                                                                    $itemPillStyle = $isItemSelected
                                                                        ? 'background-color: #22c55e; color: white; box-shadow: 0 0 0 2px #86efac;'
                                                                        : 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;';
                                                                    $itemBadgeStyle = $isItemSelected
                                                                        ? 'background-color: #4ade80; color: white;'
                                                                        : 'background-color: #e5e7eb; color: #374151;';

                                                                    $html .= '<button type="button" wire:click="selectTreatmentPlanItem(\'' . $item->id . '\')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all cursor-pointer" style="' . $itemPillStyle . '">';
                                                                    if ($isItemSelected) {
                                                                        $html .= '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                                                                    }
                                                                    $html .= '<span class="truncate max-w-[120px]">' . e($item->service->translated_name) . '</span>';
                                                                    $html .= '<span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" style="' . $itemBadgeStyle . '">' . $item->remaining_sessions . '/' . $item->recommended_sessions . '</span>';
                                                                    $html .= '<span class="text-[10px] opacity-75">' . $nextDate . '</span>';
                                                                    $html .= '</button>';
                                                                }
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
                                                                                $s->id => "{$s->translated_name} ({$s->duration_minutes} min - {$s->formatted_price})"
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
                                                                                $set('price_minor', $service->base_price_minor);
                                                                            }
                                                                        }
                                                                    })
                                                                    ->columnSpan(5),

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
                                                                    ->columnSpan(3),

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

                                                        // Hidden field for price
                                                        Forms\Components\Hidden::make('price_minor'),
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
                                                    ->columnSpanFull(),

                                                // Patient's Existing Packages - now shown in patient_info two-column layout
                                                // Keeping Hidden fields for the selected package
                                                // The visible package pills are rendered in patient_info placeholder

                                                // Hidden field to store selected subscription
                                                Forms\Components\Hidden::make('package_subscription_id'),

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
                                            ->visible(fn (Get $get) => $get('booking_type') === 'package'),

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

        \Log::warning('generateSlots called', [
            'booking_type' => $bookingType,
            'package_mode' => $data['package_mode'] ?? null,
            'package_subscription_id' => $data['package_subscription_id'] ?? null,
            'package_service_id' => $data['package_service_id'] ?? null,
            'new_package_id' => $data['new_package_id'] ?? null,
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
        $treatmentPlanItemId = null;

        if ($bookingType === 'service') {
            $services = $data['services'] ?? [];
            foreach ($services as $service) {
                if (!empty($service['service_id'])) {
                    $serviceIds[] = $service['service_id'];
                    $durations[$service['service_id']] = $service['duration_override'] ?? null;
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
                    if ($bookingType === 'package') {
                        $packageMode = $data['package_mode'] ?? 'existing';
                        if ($packageMode === 'existing') {
                            $slot['from_package'] = $data['package_subscription_id'] ?? null;
                            $slot['new_package_id'] = null;
                        } else {
                            $slot['from_package'] = null;
                            $slot['new_package_id'] = $data['new_package_id'] ?? null;
                        }
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
        $newPackageId = null;
        $treatmentPlanItemId = null;

        if ($bookingType === 'package') {
            $packageMode = $data['package_mode'] ?? 'existing';
            if ($packageMode === 'existing') {
                $fromPackage = $data['package_subscription_id'] ?? null;
            } else {
                $newPackageId = $data['new_package_id'] ?? null;
            }
        } elseif ($bookingType === 'treatment_plan') {
            $treatmentPlanItemId = $data['treatment_plan_item_id'] ?? null;
        }

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
                $slot['new_package_id'] = $newPackageId;
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
     */
    public function selectPackageForBooking(string $subscriptionId): void
    {
        // Switch to package booking mode
        $this->data['booking_type'] = 'package';
        $this->data['package_mode'] = 'existing';
        $this->data['package_subscription_id'] = $subscriptionId;

        // Clear any previous slot selections
        $this->availableSlots = [];

        Notification::make()
            ->title(__('booking::booking.messages.package_selected'))
            ->body(__('booking::booking.messages.select_service_to_book'))
            ->success()
            ->duration(2000)
            ->send();
    }

    /**
     * Select a treatment plan for booking (called from patient info card)
     */
    public function selectTreatmentPlanForBooking(string $planId): void
    {
        // Switch to treatment plan booking mode
        $this->data['booking_type'] = 'treatment_plan';
        $this->data['treatment_plan_id'] = $planId;

        // Clear any previous slot selections
        $this->availableSlots = [];

        // Try to auto-select the first bookable item
        try {
            $plan = TreatmentPlan::with('items.service')->find($planId);
            if ($plan) {
                $bookableItem = $plan->items->first(fn ($item) => $item->canBook());
                if ($bookableItem) {
                    $this->data['treatment_plan_item_id'] = $bookableItem->id;

                    // Set suggested dates based on item's next suggested date
                    if ($bookableItem->next_suggested_date) {
                        $this->data['date_from'] = $bookableItem->next_suggested_date->format('Y-m-d');
                        $this->data['date_to'] = $bookableItem->next_suggested_date->addWeeks(2)->format('Y-m-d');
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if treatment plans not fully set up
        }

        Notification::make()
            ->title(__('booking::booking.messages.treatment_plan_selected'))
            ->body(__('booking::booking.messages.select_service_to_book'))
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
                    'status' => Appointment::STATUS_SCHEDULED,
                    'source' => $data['source'] ?? Appointment::SOURCE_PHONE,
                    'notes' => $data['notes'] ?? null,
                    // Package fields
                    'package_subscription_id' => $packageSubscriptionId,
                    'is_package_session' => $isPackageSession,
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

            Notification::make()
                ->title(__('booking::booking.messages.booking_created'))
                ->body(__('booking::booking.messages.appointments_created', ['count' => $count]))
                ->success()
                ->send();

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
