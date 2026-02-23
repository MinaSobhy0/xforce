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
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Modules\Booking\Models\Appointment;
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
        // Get date from query parameter (from calendar click)
        $dateFromQuery = request()->query('date');
        $startTimeFromQuery = request()->query('start_time');

        $dateFrom = $dateFromQuery ? Carbon::parse($dateFromQuery) : today();

        $this->form->fill([
            'branch_id' => current_branch_id(),
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateFrom->copy()->addWeek()->format('Y-m-d'),
            'booking_type' => 'service',
            'services' => [['service_id' => null, 'duration_override' => null, 'price_minor' => null]],
            'source' => Appointment::SOURCE_PHONE,
            'preferred_start_time' => $startTimeFromQuery,
        ]);
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

                                        // Patient Info Card - shows packages and treatment plans
                                        Forms\Components\Placeholder::make('patient_info')
                                            ->label('')
                                            ->content(function (Get $get) {
                                                $patientId = $get('patient_id');
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
                                                        ->with('package')
                                                        ->get();

                                                    // Get active treatment plans
                                                    $activePlans = [];
                                                    try {
                                                        $activePlans = TreatmentPlan::query()
                                                            ->forPatient($patientId)
                                                            ->active()
                                                            ->get();
                                                    } catch (\Exception $e) {
                                                        // Treatment plans table may not exist
                                                    }

                                                    $html = '<div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800">';

                                                    // Packages section
                                                    $html .= '<div class="mb-3">';
                                                    $html .= '<div class="flex items-center gap-2 mb-2">';
                                                    $html .= '<svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>';
                                                    $html .= '<span class="font-semibold text-gray-900 dark:text-white">' . __('booking::booking.labels.active_packages') . '</span>';
                                                    $html .= '<span class="ml-auto px-2 py-0.5 text-xs font-medium rounded-full ' . ($activePackages->count() > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500') . '">' . $activePackages->count() . '</span>';
                                                    $html .= '</div>';

                                                    if ($activePackages->count() > 0) {
                                                        $html .= '<div class="space-y-1 ml-7">';
                                                        foreach ($activePackages->take(3) as $sub) {
                                                            $html .= '<div class="text-sm text-gray-600 dark:text-gray-300">';
                                                            $html .= '• ' . $sub->package->translated_name . ' <span class="text-green-600">(' . $sub->sessions_remaining . ' ' . __('booking::booking.labels.remaining') . ')</span>';
                                                            $html .= '</div>';
                                                        }
                                                        if ($activePackages->count() > 3) {
                                                            $html .= '<div class="text-xs text-gray-400">+' . ($activePackages->count() - 3) . ' more...</div>';
                                                        }
                                                        $html .= '</div>';
                                                    } else {
                                                        $html .= '<div class="text-sm text-gray-400 ml-7">' . __('booking::booking.labels.no_active_packages') . '</div>';
                                                    }
                                                    $html .= '</div>';

                                                    // Treatment Plans section
                                                    $html .= '<div>';
                                                    $html .= '<div class="flex items-center gap-2 mb-2">';
                                                    $html .= '<svg class="w-5 h-5 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>';
                                                    $html .= '<span class="font-semibold text-gray-900 dark:text-white">' . __('booking::booking.labels.active_treatment_plans') . '</span>';
                                                    $html .= '<span class="ml-auto px-2 py-0.5 text-xs font-medium rounded-full ' . (count($activePlans) > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500') . '">' . count($activePlans) . '</span>';
                                                    $html .= '</div>';

                                                    if (count($activePlans) > 0) {
                                                        $html .= '<div class="space-y-1 ml-7">';
                                                        foreach (array_slice($activePlans, 0, 3) as $plan) {
                                                            $html .= '<div class="text-sm text-gray-600 dark:text-gray-300">';
                                                            $html .= '• ' . $plan->name . ' <span class="text-blue-600">(' . $plan->progress_percentage . '% ' . __('booking::booking.labels.complete') . ')</span>';
                                                            $html .= '</div>';
                                                        }
                                                        if (count($activePlans) > 3) {
                                                            $html .= '<div class="text-xs text-gray-400">+' . (count($activePlans) - 3) . ' more...</div>';
                                                        }
                                                        $html .= '</div>';
                                                    } else {
                                                        $html .= '<div class="text-sm text-gray-400 ml-7">' . __('booking::booking.labels.no_active_plans') . '</div>';
                                                    }
                                                    $html .= '</div>';

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

                                                // Patient's Existing Packages as Cards
                                                Forms\Components\Placeholder::make('existing_packages_cards')
                                                    ->label('')
                                                    ->content(function (Get $get) {
                                                        $patientId = $get('patient_id');
                                                        $selectedSubscriptionId = $get('package_subscription_id');

                                                        if (!$patientId) {
                                                            return new HtmlString('<div class="text-sm text-gray-500 text-center py-4">' . __('booking::booking.messages.select_patient_first') . '</div>');
                                                        }

                                                        try {
                                                            $subscriptions = PackageSubscription::query()
                                                                ->forPatient($patientId)
                                                                ->active()
                                                                ->with('package')
                                                                ->get();

                                                            if ($subscriptions->isEmpty()) {
                                                                return new HtmlString('<div class="text-sm text-gray-500 text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">' . __('booking::booking.messages.no_active_packages') . '</div>');
                                                            }

                                                            $html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
                                                            foreach ($subscriptions as $sub) {
                                                                $isSelected = $selectedSubscriptionId === $sub->id;
                                                                $borderColor = $isSelected ? 'border-primary-500 ring-2 ring-primary-200' : 'border-gray-200 hover:border-primary-300';
                                                                $bgColor = $isSelected ? 'bg-primary-50' : 'bg-white';

                                                                $html .= '<div wire:click="$set(\'data.package_subscription_id\', \'' . $sub->id . '\')" class="cursor-pointer rounded-lg border-2 ' . $borderColor . ' ' . $bgColor . ' p-4 transition-all">';
                                                                $html .= '<div class="flex items-start justify-between">';
                                                                $html .= '<div>';
                                                                $html .= '<h4 class="font-semibold text-gray-900">' . e($sub->package->translated_name) . '</h4>';
                                                                $html .= '<p class="text-sm text-gray-500">' . $sub->sessions_remaining . ' / ' . $sub->package->total_sessions . ' ' . __('booking::booking.labels.sessions') . ' ' . __('booking::booking.labels.remaining') . '</p>';
                                                                $html .= '</div>';
                                                                if ($isSelected) {
                                                                    $html .= '<span class="flex h-6 w-6 items-center justify-center rounded-full" style="background-color: #22c55e;"><svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></span>';
                                                                }
                                                                $html .= '</div>';
                                                                // Progress bar
                                                                $progress = $sub->package->total_sessions > 0 ? (($sub->package->total_sessions - $sub->sessions_remaining) / $sub->package->total_sessions) * 100 : 0;
                                                                $html .= '<div class="mt-3 h-2 w-full rounded-full bg-gray-200">';
                                                                $html .= '<div class="h-2 rounded-full bg-primary-500" style="width: ' . $progress . '%"></div>';
                                                                $html .= '</div>';
                                                                $html .= '<p class="mt-1 text-xs text-gray-400">' . __('booking::booking.labels.expires') . ': ' . ($sub->expires_at ? $sub->expires_at->format('M d, Y') : 'N/A') . '</p>';
                                                                $html .= '</div>';
                                                            }
                                                            $html .= '</div>';

                                                            return new HtmlString($html);
                                                        } catch (\Exception $e) {
                                                            return new HtmlString('<div class="text-sm text-red-500">' . $e->getMessage() . '</div>');
                                                        }
                                                    })
                                                    ->visible(fn (Get $get) => $get('package_mode') !== 'new')
                                                    ->columnSpanFull(),

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

                                                // Services from Selected Package as Cards
                                                Forms\Components\ViewField::make('package_services_cards')
                                                    ->view('booking::components.package-services-grid')
                                                    ->viewData(fn (Get $get, $livewire) => [
                                                        'packageMode' => $get('package_mode') ?? 'existing',
                                                        'subscriptionId' => $get('package_subscription_id'),
                                                        'packageId' => $get('new_package_id'),
                                                        'selectedServiceId' => $get('package_service_id') ?? $get('new_package_service_id'),
                                                        'bookingItems' => $livewire->bookingItems ?? [],
                                                    ])
                                                    ->visible(fn (Get $get) => $get('package_subscription_id') || $get('new_package_id'))
                                                    ->columnSpanFull(),

                                                // Hidden fields to store selected service
                                                Forms\Components\Hidden::make('package_service_id'),
                                                Forms\Components\Hidden::make('new_package_service_id'),
                                            ])
                                            ->visible(fn (Get $get) => $get('booking_type') === 'package'),

                                        // Treatment Plan Selection (for treatment plan booking)
                                        Forms\Components\Fieldset::make(__('booking::booking.fields.treatment_plan'))
                                            ->schema([
                                                Forms\Components\Select::make('treatment_plan_id')
                                                    ->label(__('booking::booking.fields.select_treatment_plan'))
                                                    ->options(function (Get $get) {
                                                        $patientId = $get('patient_id');
                                                        if (!$patientId) {
                                                            return [];
                                                        }
                                                        try {
                                                            return TreatmentPlan::query()
                                                                ->forPatient($patientId)
                                                                ->active()
                                                                ->with('items.service')
                                                                ->get()
                                                                ->mapWithKeys(fn (TreatmentPlan $plan) => [
                                                                    $plan->id => "{$plan->code}: {$plan->translated_name} ({$plan->progress_percentage}% complete)"
                                                                ]);
                                                        } catch (\Exception $e) {
                                                            return [];
                                                        }
                                                    })
                                                    ->searchable()
                                                    ->live()
                                                    ->required(fn (Get $get) => $get('booking_type') === 'treatment_plan')
                                                    ->helperText(fn (Get $get) => !$get('patient_id')
                                                        ? __('booking::booking.messages.select_patient_first')
                                                        : null),

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

                                                Forms\Components\Select::make('treatment_plan_item_id')
                                                    ->label(__('booking::booking.fields.select_service_to_book'))
                                                    ->options(function (Get $get) {
                                                        $planId = $get('treatment_plan_id');
                                                        if (!$planId) {
                                                            return [];
                                                        }
                                                        try {
                                                            $plan = TreatmentPlan::with('items.service')->find($planId);
                                                            if (!$plan) {
                                                                return [];
                                                            }
                                                            return $plan->items
                                                                ->filter(fn ($item) => $item->canBook())
                                                                ->mapWithKeys(fn ($item) => [
                                                                    $item->id => "{$item->service->translated_name} ({$item->remaining_sessions} remaining, next: {$item->next_suggested_date->format('M d')})"
                                                                ]);
                                                        } catch (\Exception $e) {
                                                            return [];
                                                        }
                                                    })
                                                    ->required(fn (Get $get) => $get('booking_type') === 'treatment_plan')
                                                    ->visible(fn (Get $get) => $get('treatment_plan_id'))
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        if ($state) {
                                                            try {
                                                                $item = TreatmentPlanItem::find($state);
                                                                if ($item && $item->next_suggested_date) {
                                                                    $set('date_from', $item->next_suggested_date->format('Y-m-d'));
                                                                    $set('date_to', $item->next_suggested_date->addWeeks(2)->format('Y-m-d'));
                                                                }
                                                            } catch (\Exception $e) {
                                                                // Table may not exist
                                                            }
                                                        }
                                                    }),

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
                                                    ->maxDate(today()->addDays(config('booking.max_advance_booking_days', 60)))
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
                                                    ->maxDate(today()->addDays(config('booking.max_advance_booking_days', 60)))
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
            // First, create any new package subscriptions needed
            foreach ($this->bookingItems as $item) {
                if (!empty($item['new_package_id']) && !isset($newPackageSubscriptions[$item['new_package_id']])) {
                    $package = Package::find($item['new_package_id']);
                    if ($package) {
                        $subscription = PackageSubscription::create([
                            'patient_id' => $data['patient_id'],
                            'package_id' => $package->id,
                            'status' => PackageSubscription::STATUS_ACTIVE,
                            'purchased_at' => now(),
                            'expires_at' => now()->addDays($package->validity_days),
                            'amount_paid_minor' => $package->base_price_minor,
                        ]);
                        $newPackageSubscriptions[$item['new_package_id']] = $subscription->id;
                    }
                }
            }

            foreach ($this->bookingItems as $item) {
                $service = Service::find($item['service_id']);

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
                    'price_minor' => $service?->base_price_minor ?? 0,
                    'status' => Appointment::STATUS_SCHEDULED,
                    'source' => $data['source'] ?? Appointment::SOURCE_PHONE,
                    'notes' => $data['notes'] ?? null,
                ]);

                $createdAppointments[] = $appointment;

                // Record package usage if from existing package
                if (!empty($item['from_package'])) {
                    PackageSessionUsage::create([
                        'subscription_id' => $item['from_package'],
                        'service_id' => $item['service_id'],
                        'appointment_id' => $appointment->id,
                        'used_at' => now(),
                    ]);

                    // Check if package is now complete
                    $subscription = PackageSubscription::find($item['from_package']);
                    $subscription?->checkAndMarkComplete();
                }

                // Record package usage if from newly purchased package
                if (!empty($item['new_package_id']) && isset($newPackageSubscriptions[$item['new_package_id']])) {
                    PackageSessionUsage::create([
                        'subscription_id' => $newPackageSubscriptions[$item['new_package_id']],
                        'service_id' => $item['service_id'],
                        'appointment_id' => $appointment->id,
                        'used_at' => now(),
                    ]);
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

            $count = count($createdAppointments);

            Notification::make()
                ->title(__('booking::booking.messages.booking_created'))
                ->body(__('booking::booking.messages.appointments_created', ['count' => $count]))
                ->success()
                ->send();

            $this->redirect(route('filament.tenant.resources.appointments.index'));

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::booking.messages.booking_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
