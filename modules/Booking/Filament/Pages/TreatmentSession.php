<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\SessionConsumable;
use Modules\Booking\Models\SessionProduct;
use Modules\Booking\Models\TreatmentSessionData;
use Modules\Booking\Models\Visit;
use Modules\Booking\Services\VisitService;
use Modules\Equipment\Models\Equipment;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Services\StockMoveService;
use Modules\Patients\Models\MedicalProfile;
use Modules\Patients\Models\Patient;
use Modules\Patients\Models\PatientAmrSummary;
use Modules\Patients\Models\PatientMedicalHistory;
use Modules\Patients\Models\PatientNote;
use Modules\Patients\Models\PatientPhoto;
use Modules\Services\Models\ParameterPreset;
use Modules\Services\Models\Service;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;

class TreatmentSession extends Page implements HasActions, HasForms, HasInfolists
{
    use ChecksResourcePermissions;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithInfolists;
    use WithFileUploads;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'treatment_session';

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'treatment-session';

    protected static bool $shouldRegisterNavigation = true;

    protected static string $view = 'booking::filament.pages.treatment-session';

    public static function getNavigationLabel(): string
    {
        return __('booking::session.navigation_label');
    }

    // Query string parameter for appointment
    #[Url]
    public ?string $appointment_id = null;

    // View mode for completed sessions
    #[Url]
    public ?string $view_mode = null;

    public ?Appointment $appointment = null;

    public ?Visit $visit = null;

    public ?Patient $patient = null;

    public ?PatientMedicalHistory $medicalHistory = null;

    public ?MedicalProfile $medicalProfile = null;

    public ?PatientAmrSummary $amrSummary = null;

    public ?TreatmentSessionData $sessionData = null;

    // Dynamic parameters
    public array $parameterValues = [];

    public array $equipmentMetrics = [];

    public array $treatmentAreas = [];

    public array $preTreatmentChecklist = [];

    // Equipment selection
    public array $sessionEquipment = [];

    public array $equipmentParameterValues = []; // Equipment-specific parameter values

    public ?string $newEquipmentId = null;

    public ?string $selectedPresetId = null;

    // Clinical notes
    public ?string $clinicalNotes = null;

    public ?string $skinReaction = 'none';

    public ?string $painLevel = null;

    // Forms
    public ?string $noteContent = null;

    public ?string $noteType = 'treatment';

    public $photoUpload = null;

    public ?string $photoType = 'progress';

    public ?string $photoBodyArea = null;

    public ?string $photoDescription = null;

    // Photo upload form data (for Filament FileUpload)
    public ?array $photoFormData = [
        'photo' => null,
        'photo_name' => null,
        'type' => 'progress',
    ];

    // Camera photo capture
    public $cameraPhoto = null;

    public string $cameraPhotoType = 'progress';

    // Treatment plan form
    public ?array $treatmentPlanData = [];

    // Start another session
    public ?int $pendingSessionItemId = null;

    // Consumables & Products
    public array $sessionConsumables = [];

    public array $sessionProducts = [];

    public ?string $newConsumableId = null;

    public ?float $newConsumableQty = 1;

    public ?string $newProductId = null;

    public ?float $newProductQty = 1;

    // Prescription data
    public array $prescriptionMedications = [];

    public ?string $prescriptionDiagnosis = null;

    public ?string $prescriptionNotes = null;

    // Invoice section
    public ?int $servicePriceMinor = null;

    public ?string $serviceDiscountType = 'none';

    public ?int $serviceDiscountValue = 0;

    public ?string $overallDiscountType = 'none';

    public ?int $overallDiscountValue = 0;

    public ?string $overallDiscountReason = null;

    public ?string $editingInvoiceItem = null; // Track which item is being edited: 'service' or 'product-{id}'

    public function mount(): void
    {
        $this->loadAppointment();

        if (! $this->appointment) {
            Notification::make()
                ->title(__('booking::session.messages.appointment_not_found'))
                ->danger()
                ->send();
            $this->redirect(DoctorDashboard::getUrl());

            return;
        }

        // Allow viewing completed sessions in view mode
        $isViewMode = $this->view_mode === '1';
        $isCompleted = $this->appointment->status === Appointment::STATUS_COMPLETED;
        $isInProgress = $this->appointment->status === Appointment::STATUS_IN_PROGRESS;

        // Ensure appointment is in progress OR completed (for view mode)
        if (! $isInProgress && ! ($isViewMode && $isCompleted)) {
            Notification::make()
                ->title(__('booking::session.messages.session_not_active'))
                ->danger()
                ->send();
            $this->redirect(DoctorDashboard::getUrl());

            return;
        }

        $this->treatmentPlanData = [
            'name' => '',
            'services' => [
                ['service_id' => null, 'sessions' => 1, 'interval' => 7],
            ],
            'notes' => '',
        ];

        // Load or create session data
        $this->loadOrCreateSessionData();

        // Initialize invoice data
        $this->loadInvoiceData();
    }

    protected function loadInvoiceData(): void
    {
        if (! $this->appointment) {
            return;
        }

        // Load service price from appointment
        $this->servicePriceMinor = $this->appointment->price_minor
            ?? $this->appointment->service?->base_price_minor
            ?? 0;

        // Load existing service discount from appointment
        if ($this->appointment->discount_minor > 0) {
            $this->serviceDiscountType = $this->appointment->discount_type ?? 'fixed';
            // For percentage: stored value is the percentage (e.g., 10 for 10%)
            // For fixed: stored value is in minor units, but user sees/edits in major units
            $this->serviceDiscountValue = $this->serviceDiscountType === 'fixed'
                ? (int) ($this->appointment->discount_minor / 100)
                : $this->appointment->discount_minor;
        }
    }

    protected function loadAppointment(): void
    {
        $this->appointment = Appointment::with([
            'patient.medicalHistory',
            'patient.medicalProfile.allergies',
            'patient.medicalProfile.medications',
            'patient.medicalProfile.contraindications',
            'patient.amrSummary',
            'service',
            'practitioner',
            'room',
            'branch',
            'treatmentPlanAppointment.item.treatmentPlan',
            'packageSubscription.package.items',
            'visits',
        ])->find($this->appointment_id);

        if ($this->appointment) {
            $this->patient = $this->appointment->patient;
            $this->medicalHistory = $this->patient?->medicalHistory;
            $this->medicalProfile = $this->patient?->medicalProfile;
            $this->amrSummary = $this->patient?->amrSummary;

            // Load the current visit for this appointment
            $this->visit = $this->appointment->current_visit;

            // If no visit exists yet (checked in before visit system), create one now
            if (! $this->visit && $this->appointment->patient && $this->appointment->isCheckedIn()) {
                $visitService = app(\Modules\Booking\Services\VisitService::class);
                $this->visit = $visitService->findOrCreateVisit(
                    $this->appointment->patient,
                    $this->appointment->branch,
                    Visit::SOURCE_APPOINTMENT
                );
                $visitService->addAppointment($this->visit, $this->appointment);
            }
        }
    }

    /**
     * Check if the page is in view mode (read-only).
     */
    public function isViewMode(): bool
    {
        return $this->view_mode === '1' || $this->appointment?->status === Appointment::STATUS_COMPLETED;
    }

    /**
     * Get previous sessions for the same patient and service.
     */
    public function getPreviousSessions(): Collection
    {
        if (! $this->appointment || ! $this->patient) {
            return collect();
        }

        return Appointment::query()
            ->with(['service', 'practitioner', 'sessionData'])
            ->where('patient_id', $this->patient->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->where('id', '!=', $this->appointment->id)
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->limit(10)
            ->get();
    }

    /**
     * Get previous sessions for the same service.
     */
    public function getPreviousServiceSessions(): Collection
    {
        if (! $this->appointment || ! $this->patient) {
            return collect();
        }

        return Appointment::query()
            ->with(['service', 'practitioner', 'sessionData'])
            ->where('patient_id', $this->patient->id)
            ->where('service_id', $this->appointment->service_id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->where('id', '!=', $this->appointment->id)
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->limit(5)
            ->get();
    }

    /**
     * View a specific previous session.
     */
    public function viewPreviousSession(int $appointmentId): void
    {
        $this->redirect(static::getUrl().'?appointment_id='.$appointmentId.'&view_mode=1');
    }

    protected function loadOrCreateSessionData(): void
    {
        if (! $this->appointment) {
            return;
        }

        // Try to load existing session data
        $this->sessionData = TreatmentSessionData::where('appointment_id', $this->appointment->id)->first();

        if (! $this->sessionData) {
            // Create new session data
            $this->sessionData = TreatmentSessionData::create([
                'tenant_id' => $this->appointment->tenant_id,
                'appointment_id' => $this->appointment->id,
                'service_id' => $this->appointment->service_id,
                'equipment_id' => $this->appointment->equipment_id,
                'practitioner_id' => $this->appointment->practitioner_id,
                'session_started_at' => $this->appointment->started_at ?? now(),
            ]);

            // Update treatment plan item status to in_progress if this is a plan appointment
            if ($planAppointment = $this->appointment->treatmentPlanAppointment) {
                $item = $planAppointment->item;
                if ($item && $item->isPending()) {
                    $item->status = TreatmentPlanItem::STATUS_IN_PROGRESS;
                    $item->save();
                }
            }
        }

        // Load state from session data
        $this->parameterValues = $this->sessionData->parameter_values ?? [];
        $this->equipmentMetrics = $this->sessionData->equipment_metrics ?? [];
        $this->treatmentAreas = $this->sessionData->treatment_areas ?? [];
        $this->preTreatmentChecklist = $this->sessionData->pre_treatment_checklist ?? $this->getDefaultChecklist();
        $this->selectedPresetId = $this->sessionData->preset_id;
        $this->clinicalNotes = $this->sessionData->clinical_notes;
        $this->skinReaction = $this->sessionData->skin_reaction ?? 'none';
        $this->painLevel = $this->sessionData->pain_level;

        // Load session equipment
        $this->loadSessionEquipment();

        // If no parameter values, try to apply default preset or service defaults
        if (empty($this->parameterValues) && $this->appointment->service) {
            // First, try to find and apply the default preset for this service
            $defaultPreset = ParameterPreset::query()
                ->where('service_id', $this->appointment->service_id)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            if ($defaultPreset) {
                $this->selectedPresetId = $defaultPreset->id;
                $this->parameterValues = $defaultPreset->getValues();

                // Save to session data
                if ($this->sessionData) {
                    $this->sessionData->update([
                        'preset_id' => $defaultPreset->id,
                        'parameter_values' => $this->parameterValues,
                    ]);
                }
            } else {
                // Fall back to service default values
                $this->parameterValues = $this->appointment->service->getDefaultParameterValues();
            }
        }

        // Load consumables and products
        $this->loadConsumablesAndProducts();
    }

    protected function loadConsumablesAndProducts(): void
    {
        if (! $this->appointment) {
            return;
        }

        // Load existing session consumables
        $existingConsumables = SessionConsumable::where('appointment_id', $this->appointment->id)
            ->with('product')
            ->withCount('faceChartMarkers')
            ->get();

        // If no consumables exist, auto-populate from service/category using override pattern
        if ($existingConsumables->isEmpty() && $this->appointment->service) {
            $this->autoPopulateConsumablesFromService();

            // Reload after auto-population
            $existingConsumables = SessionConsumable::where('appointment_id', $this->appointment->id)
                ->with('product')
                ->withCount('faceChartMarkers')
                ->get();
        }

        $this->sessionConsumables = $existingConsumables
            ->map(fn ($c) => [
                'id' => $c->id,
                'product_id' => $c->product_id,
                'product_name' => $c->product?->getTranslation('name', app()->getLocale()) ?? '',
                'quantity' => $c->quantity,
                'base_quantity' => $c->base_quantity ?? $c->quantity, // fallback for old records
                'unit' => $c->unit_abbreviation,
                'unit_cost' => $c->unit_cost,
                'total_cost' => $c->total_cost,
                'markers_count' => $c->face_chart_markers_count ?? 0,
            ])
            ->toArray();

        $this->sessionProducts = SessionProduct::where('appointment_id', $this->appointment->id)
            ->with(['product', 'uom'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'product_id' => $p->product_id,
                'product_name' => $p->product?->getTranslation('name', app()->getLocale()) ?? '',
                'quantity' => $p->quantity,
                'unit' => $p->unit_abbreviation,
                'unit_price' => $p->unit_price,
                'total_price' => $p->total_price,
                'usage_type' => $p->usage_type,
                'discount_type' => $p->discount_type ?? 'none',
                'discount_value' => $p->discount_value ?? 0,
            ])
            ->toArray();
    }

    /**
     * Auto-populate consumables from service or category using override pattern.
     * Service consumables take precedence over category consumables.
     */
    protected function autoPopulateConsumablesFromService(): void
    {
        $service = $this->appointment->service;
        if (! $service) {
            return;
        }

        // Use the effective consumables method which implements the override pattern
        $effectiveConsumables = $service->getEffectiveConsumables();

        foreach ($effectiveConsumables as $consumableData) {
            $product = $consumableData['product'];
            $quantity = $consumableData['quantity'] ?? 1;

            if (! $product) {
                continue;
            }

            SessionConsumable::create([
                'tenant_id' => $this->appointment->tenant_id,
                'appointment_id' => $this->appointment->id,
                'product_id' => $product->id,
                'branch_id' => $this->appointment->branch_id,
                'quantity' => $quantity,
                'uom_id' => $product->sales_uom_id,
                'unit' => $product->unit_abbreviation, // Fallback for display
                'unit_cost_minor' => $product->cost_price_minor ?? 0,
                'created_by' => auth()->id(),
            ]);
        }
    }

    protected function getDefaultChecklist(): array
    {
        return [
            'patient_identity_verified' => false,
            'consent_signed' => false,
            'medical_history_reviewed' => false,
            'contraindications_checked' => false,
            'allergies_confirmed' => false,
            'test_patch_done' => false,
            'eye_protection_provided' => false,
            'treatment_area_clean' => false,
        ];
    }

    public function getTitle(): string
    {
        return __('booking::session.title');
    }

    public function getHeading(): string
    {
        if ($this->patient) {
            return $this->patient->full_name.' - '.$this->appointment?->service?->translated_name;
        }

        return __('booking::session.heading');
    }

    public function getSubheading(): ?string
    {
        if ($this->appointment?->treatmentPlanAppointment) {
            $planAppt = $this->appointment->treatmentPlanAppointment;

            return __('booking::session.session_of', [
                'current' => $planAppt->session_number,
                'total' => $planAppt->item->recommended_sessions,
            ]);
        }

        return null;
    }

    /**
     * Open the Add to Treatment Plan modal.
     * Called from the plus button in the Current Treatment Plan section.
     */
    public function openAddToPlanModal(): void
    {
        $this->mountAction('addToTreatmentPlan');
    }

    protected function getHeaderActions(): array
    {
        // In view mode, only show a back button
        if ($this->isViewMode()) {
            return [
                Action::make('backToDashboard')
                    ->label(__('booking::session.view_mode.back_to_dashboard'))
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(DoctorDashboard::getUrl()),
            ];
        }

        return [
            Action::make('addToTreatmentPlan')
                ->label(__('booking::session.actions.add_to_plan'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->extraAttributes(['style' => 'display: none;']) // Hidden from header, triggered via plus button in Current Treatment Plan section
                ->form([
                    Forms\Components\Radio::make('plan_mode')
                        ->label(__('booking::session.plan_modal.mode'))
                        ->options([
                            'existing' => __('booking::session.plan_modal.add_to_existing'),
                            'new' => __('booking::session.plan_modal.create_new'),
                        ])
                        ->default('existing')
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('treatment_plan_id')
                        ->label(__('booking::session.plan_modal.select_plan'))
                        ->options(fn () => $this->getActiveTreatmentPlans()
                            ->mapWithKeys(fn ($plan) => [$plan->id => $plan->name]))
                        ->default(fn () => $this->getCurrentTreatmentPlan()?->id)
                        ->visible(fn (Forms\Get $get) => $get('plan_mode') === 'existing')
                        ->required(fn (Forms\Get $get) => $get('plan_mode') === 'existing'),
                    Forms\Components\TextInput::make('new_plan_name')
                        ->label(__('booking::session.plan_modal.plan_name'))
                        ->visible(fn (Forms\Get $get) => $get('plan_mode') === 'new')
                        ->required(fn (Forms\Get $get) => $get('plan_mode') === 'new'),
                    Forms\Components\Repeater::make('items')
                        ->label(__('booking::session.plan_modal.items'))
                        ->schema([
                            Forms\Components\Select::make('item_type')
                                ->label(__('booking::session.plan_modal.item_type'))
                                ->options([
                                    TreatmentPlanItem::TYPE_SERVICE => 'Service',
                                    TreatmentPlanItem::TYPE_PACKAGE => 'Package',
                                ])
                                ->default('service')
                                ->required()
                                ->live(),
                            Forms\Components\Select::make('service_id')
                                ->label(__('booking::session.plan_modal.service'))
                                ->options(fn () => $this->getAvailableServices())
                                ->visible(fn (Forms\Get $get) => $get('item_type') === 'service')
                                ->required(fn (Forms\Get $get) => $get('item_type') === 'service')
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set, $state) => $this->setItemPrice($set, $state, 'service')),
                            Forms\Components\Select::make('package_id')
                                ->label(__('booking::session.plan_modal.package'))
                                ->options(fn () => $this->getAvailablePackages())
                                ->visible(fn (Forms\Get $get) => $get('item_type') === 'package')
                                ->required(fn (Forms\Get $get) => $get('item_type') === 'package')
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set, $state) => $this->setItemPrice($set, $state, 'package')),
                            Forms\Components\TextInput::make('sessions')
                                ->label(__('booking::session.plan_modal.sessions'))
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(50)
                                ->visible(fn (Forms\Get $get) => $get('item_type') === 'service'),
                            Forms\Components\TextInput::make('quantity')
                                ->label(__('booking::session.plan_modal.quantity'))
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->visible(fn (Forms\Get $get) => $get('item_type') === 'package'),
                            Forms\Components\TextInput::make('interval_days')
                                ->label(__('booking::session.plan_modal.interval'))
                                ->numeric()
                                ->default(7)
                                ->minValue(1)
                                ->suffix(__('booking::session.plan.days'))
                                ->visible(fn (Forms\Get $get) => $get('item_type') === 'service'),
                            Forms\Components\Select::make('preferred_practitioner_id')
                                ->label(__('booking::session.plan_modal.assign_to_doctor'))
                                ->options(function (Forms\Get $get) {
                                    $serviceId = $get('service_id');
                                    if (! $serviceId) {
                                        return [];
                                    }
                                    $service = Service::find($serviceId);
                                    if (! $service) {
                                        return [];
                                    }
                                    $qualified = $service->qualifiedStaff()->with('user')->get();
                                    if ($qualified->isEmpty()) {
                                        return [];
                                    }

                                    return $qualified
                                        ->filter(fn ($staff) => $staff->user)
                                        ->mapWithKeys(fn ($staff) => [$staff->user->id => $staff->user->full_name])
                                        ->toArray();
                                })
                                ->placeholder(__('booking::session.plan_modal.current_doctor'))
                                ->visible(fn (Forms\Get $get) => $get('item_type') === 'service')
                                ->searchable(),
                            Forms\Components\Hidden::make('unit_price'),
                            Forms\Components\Placeholder::make('original_price_display')
                                ->label(__('booking::session.plan_modal.original_price'))
                                ->content(fn (Forms\Get $get) => $get('unit_price')
                                    ? current_currency().' '.number_format((float) $get('unit_price'), 2)
                                    : '-'),
                            Forms\Components\Select::make('discount_type')
                                ->label(__('booking::session.plan_modal.discount_type'))
                                ->options([
                                    'none' => __('booking::session.plan_modal.no_discount'),
                                    'percent' => __('booking::session.plan_modal.percentage'),
                                    'fixed' => __('booking::session.plan_modal.fixed_amount'),
                                ])
                                ->default('none')
                                ->live(),
                            Forms\Components\TextInput::make('discount_value')
                                ->label(__('booking::session.plan_modal.discount_value'))
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? 100 : ($get('unit_price') ?? 999999))
                                ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? '%' : null)
                                ->prefix(fn (Forms\Get $get) => $get('discount_type') === 'fixed' ? current_currency() : null)
                                ->visible(fn (Forms\Get $get) => in_array($get('discount_type'), ['percent', 'fixed']))
                                ->live(debounce: 500),
                            Forms\Components\Placeholder::make('final_price_display')
                                ->label(__('booking::session.plan_modal.final_price'))
                                ->content(function (Forms\Get $get) {
                                    $unitPrice = (float) ($get('unit_price') ?? 0);
                                    $discountType = $get('discount_type') ?? 'none';
                                    $discountValue = (float) ($get('discount_value') ?? 0);

                                    if ($discountType === 'percent') {
                                        $finalPrice = $unitPrice * (1 - $discountValue / 100);
                                    } elseif ($discountType === 'fixed') {
                                        $finalPrice = max(0, $unitPrice - $discountValue);
                                    } else {
                                        $finalPrice = $unitPrice;
                                    }

                                    return new \Illuminate\Support\HtmlString(
                                        '<span class="font-semibold text-success-600">'.current_currency().' '.number_format($finalPrice, 2).'</span>'
                                    );
                                })
                                ->visible(fn (Forms\Get $get) => $get('unit_price') > 0),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->maxItems(10)
                        ->defaultItems(1)
                        ->required(),
                ])
                ->modalHeading(__('booking::session.modals.add_to_plan'))
                ->modalWidth('4xl')
                ->action(fn (array $data) => $this->addItemsToTreatmentPlan($data)),

            Action::make('applyDiscount')
                ->label(__('booking::session.actions.apply_discount'))
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('discount_type')
                        ->label(__('booking::session.discount.type'))
                        ->options(Appointment::DISCOUNT_TYPES)
                        ->default($this->appointment?->discount_type ?? 'fixed')
                        ->required()
                        ->live(),
                    Forms\Components\TextInput::make('discount_value')
                        ->label(fn (Forms\Get $get) => $get('discount_type') === 'percent'
                            ? __('booking::session.discount.percentage')
                            : __('booking::session.discount.amount'))
                        ->numeric()
                        ->default(fn () => $this->appointment?->discount_minor ?? 0)
                        ->required()
                        ->minValue(0)
                        ->maxValue(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? 100 : $this->appointment?->price_minor ?? 999999)
                        ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? '%' : null)
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get, $state) => $this->calculateDiscountPreview($set, $get, $state)),
                    Forms\Components\Placeholder::make('discount_preview')
                        ->label(__('booking::session.discount.preview'))
                        ->content(function (Forms\Get $get) {
                            $type = $get('discount_type') ?? 'fixed';
                            $value = (int) ($get('discount_value') ?? 0);
                            $price = $this->appointment?->price_minor ?? 0;

                            if ($type === 'percent') {
                                $discountAmount = (int) round($price * $value / 100);
                            } else {
                                $discountAmount = $value;
                            }

                            $finalPrice = max(0, $price - $discountAmount);

                            return view('booking::filament.components.discount-preview', [
                                'originalPrice' => $price / 100,
                                'discountAmount' => $discountAmount / 100,
                                'finalPrice' => $finalPrice / 100,
                            ]);
                        }),
                    Forms\Components\Textarea::make('discount_reason')
                        ->label(__('booking::session.discount.reason'))
                        ->rows(2)
                        ->placeholder(__('booking::session.discount.reason_placeholder'))
                        ->default($this->appointment?->discount_reason),
                ])
                ->modalHeading(__('booking::session.modals.apply_discount'))
                ->modalSubmitActionLabel(__('booking::session.actions.apply'))
                ->action(fn (array $data) => $this->applyDiscount($data)),

            Action::make('complete')
                ->label(__('booking::session.actions.complete_session'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->size('lg')
                ->requiresConfirmation()
                ->modalHeading(__('booking::session.modals.complete_session'))
                ->modalDescription(__('booking::session.modals.complete_session_desc'))
                ->action(fn () => $this->completeSession()),

            Action::make('close')
                ->label(__('booking::session.actions.close_session'))
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->size('lg')
                ->requiresConfirmation()
                ->modalHeading(__('booking::session.modals.close_session'))
                ->modalDescription(__('booking::session.modals.close_session_desc'))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label(__('booking::session.fields.close_reason'))
                        ->placeholder(__('booking::session.placeholders.close_reason'))
                        ->rows(3),
                ])
                ->action(fn (array $data) => $this->closeSession($data['reason'] ?? null)),

            Action::make('startAnotherSession')
                ->label(__('booking::session.actions.start_another_session'))
                ->icon('heroicon-o-play')
                ->color('info')
                ->extraAttributes(['style' => 'display: none;'])
                ->form(function () {
                    $item = $this->pendingSessionItemId ? TreatmentPlanItem::withoutGlobalScope('tenant')->find($this->pendingSessionItemId) : null;
                    $qualifiedPractitioners = $item ? $this->getQualifiedPractitionersForService($item->service_id) : [];

                    return [
                        Forms\Components\Placeholder::make('service_info')
                            ->label(__('booking::session.start_another.service'))
                            ->content(fn () => $item?->service?->translated_name ?? '-'),

                        Forms\Components\Radio::make('action')
                            ->label(__('booking::session.start_another.action'))
                            ->options([
                                'complete_current' => __('booking::session.start_another.complete_current'),
                                'keep_open' => __('booking::session.start_another.keep_open'),
                                'assign_doctor' => __('booking::session.start_another.assign_doctor'),
                            ])
                            ->descriptions([
                                'complete_current' => __('booking::session.start_another.complete_current_desc'),
                                'keep_open' => __('booking::session.start_another.keep_open_desc'),
                                'assign_doctor' => __('booking::session.start_another.assign_doctor_desc'),
                            ])
                            ->default('keep_open')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('practitioner_id')
                            ->label(__('booking::session.start_another.select_doctor'))
                            ->options($qualifiedPractitioners)
                            ->visible(fn (Forms\Get $get) => $get('action') === 'assign_doctor' && ! empty($qualifiedPractitioners))
                            ->required(fn (Forms\Get $get) => $get('action') === 'assign_doctor')
                            ->searchable(),

                        Forms\Components\Placeholder::make('no_doctors_warning')
                            ->label('')
                            ->content(__('booking::session.start_another.no_other_doctors'))
                            ->visible(fn (Forms\Get $get) => $get('action') === 'assign_doctor' && empty($qualifiedPractitioners)),
                    ];
                })
                ->modalHeading(__('booking::session.modals.start_another_session'))
                ->modalWidth('md')
                ->action(fn (array $data) => $this->executeStartAnotherSession($data)),

            Action::make('back')
                ->label(__('booking::session.actions.back_to_dashboard'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DoctorDashboard::getUrl()),
        ];
    }

    public function completeSession(): void
    {
        if (! $this->appointment) {
            return;
        }

        DB::transaction(function () {
            // Complete session data
            if ($this->sessionData) {
                $this->sessionData->update([
                    'session_ended_at' => now(),
                    'actual_duration_minutes' => $this->sessionData->session_started_at
                        ? (int) $this->sessionData->session_started_at->diffInMinutes(now())
                        : null,
                    'is_complete' => true,
                    'parameter_values' => $this->parameterValues,
                    'equipment_metrics' => $this->equipmentMetrics,
                    'session_equipment' => $this->sessionEquipment,
                    'equipment_parameter_values' => $this->equipmentParameterValues,
                    'treatment_areas' => $this->treatmentAreas,
                    'pre_treatment_checklist' => $this->preTreatmentChecklist,
                    'clinical_notes' => $this->clinicalNotes,
                    'skin_reaction' => $this->skinReaction,
                    'pain_level' => $this->painLevel,
                ]);
            }

            // Update cumulative equipment parameters (shots, energy, etc.)
            $this->updateCumulativeEquipmentParameters();

            // Complete the appointment - this triggers AppointmentObserver which handles:
            // - Updating treatment plan appointment status
            // - Incrementing completed sessions
            // - Checking if plan should auto-complete
            $this->appointment->complete();

            // Get the treatment default location for this branch
            $branchId = $this->appointment->branch_id;
            $sourceLocation = StockLocation::getTreatmentDefaultLocation($branchId);

            if (! $sourceLocation) {
                // Fallback to default stock location
                $sourceLocation = StockLocation::getDefaultLocation($branchId);
            }

            $stockMoveService = app(StockMoveService::class);

            // Process consumables - deduct from inventory using Odoo-like transfers
            $pendingConsumables = SessionConsumable::where('appointment_id', $this->appointment->id)
                ->where('is_deducted', false)
                ->with('product')
                ->get();

            foreach ($pendingConsumables as $consumable) {
                $stockMovementId = null;

                // Only create stock movement for storable products that track inventory
                if ($consumable->product && $consumable->product->tracksInventory() && $sourceLocation) {
                    // Use base_quantity if set, otherwise fall back to quantity
                    $quantityToDeduct = (int) ($consumable->base_quantity ?? $consumable->quantity);

                    // Odoo-like: Transfer from Treatment Location → Customer Location
                    // Uses the UOM specified on the consumable (set when adding)
                    $transfer = $stockMoveService->createConsumption(
                        $consumable->product,
                        $sourceLocation,
                        $quantityToDeduct,
                        $consumable->uom_id, // Use consumable's UOM
                        SessionConsumable::class,
                        (string) $consumable->id,
                        'Consumed during appointment #'.$this->appointment->id
                    );

                    // Get the stock_movement_id from the transfer's first line
                    $stockMovementId = $transfer->lines->first()?->stock_movement_id;
                }

                $consumable->update([
                    'is_deducted' => true,
                    'deducted_at' => now(),
                    'deducted_by' => auth()->id(),
                    'stock_movement_id' => $stockMovementId,
                ]);
            }

            // NOTE: Product stock moves are NOT created here.
            // They are created when the invoice is generated (sale delivery).
        });

        Notification::make()
            ->title(__('booking::session.messages.session_completed'))
            ->success()
            ->send();

        $this->redirect(DoctorDashboard::getUrl());
    }

    /**
     * Close the session without completing it.
     * Patient will need to schedule a new appointment to continue.
     */
    public function closeSession(?string $reason = null): void
    {
        if (! $this->appointment) {
            return;
        }

        DB::transaction(function () use ($reason) {
            // Save session data but mark as incomplete
            if ($this->sessionData) {
                $this->sessionData->update([
                    'session_ended_at' => now(),
                    'actual_duration_minutes' => $this->sessionData->session_started_at
                        ? (int) $this->sessionData->session_started_at->diffInMinutes(now())
                        : null,
                    'is_complete' => false,
                    'parameter_values' => $this->parameterValues,
                    'equipment_metrics' => $this->equipmentMetrics,
                    'session_equipment' => $this->sessionEquipment,
                    'equipment_parameter_values' => $this->equipmentParameterValues,
                    'treatment_areas' => $this->treatmentAreas,
                    'pre_treatment_checklist' => $this->preTreatmentChecklist,
                    'clinical_notes' => $this->clinicalNotes,
                    'skin_reaction' => $this->skinReaction,
                    'pain_level' => $this->painLevel,
                    'close_reason' => $reason,
                ]);
            }

            // Revert appointment to scheduled (does NOT trigger AppointmentCompleted event)
            // Treatment plan session count is NOT incremented
            $this->appointment->revertToScheduled($reason);

            // NOTE: Consumables are NOT deducted for closed sessions
            // They remain pending and can be used in the rescheduled session
        });

        Notification::make()
            ->title(__('booking::session.messages.session_closed'))
            ->body(__('booking::session.messages.session_closed_body'))
            ->warning()
            ->send();

        $this->redirect(DoctorDashboard::getUrl());
    }

    public function applyDiscount(array $data): void
    {
        if (! $this->appointment) {
            return;
        }

        $discountType = $data['discount_type'] ?? 'fixed';
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountReason = $data['discount_reason'] ?? null;

        // Calculate discount_minor based on type
        // For percent: calculate actual discount amount and store as fixed
        // For fixed: convert from major units (EGP) to minor units (piastres)
        if ($discountType === 'percent' && $discountValue > 0) {
            // Calculate discount amount from percentage and store as fixed
            $priceMinor = $this->appointment->price_minor ?? 0;
            $discountMinor = (int) round($priceMinor * $discountValue / 100);
            $discountType = 'fixed'; // Store as fixed amount
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            // Convert from EGP to piastres
            $discountMinor = (int) ($discountValue * 100);
        } else {
            $discountMinor = 0;
            $discountType = 'fixed';
        }

        $this->appointment->update([
            'discount_type' => $discountType,
            'discount_minor' => $discountMinor,
            'discount_reason' => $discountReason,
        ]);

        $this->appointment->refresh();

        // Update local state to reflect the change
        $this->serviceDiscountType = $discountType;
        $this->serviceDiscountValue = $discountMinor / 100; // Convert back to major units for display

        Notification::make()
            ->title(__('booking::session.messages.discount_applied'))
            ->body(__('booking::session.messages.discount_applied_body', [
                'amount' => number_format($discountMinor / 100, 2),
                'final' => number_format(($this->appointment->price_minor - $discountMinor) / 100, 2),
            ]))
            ->success()
            ->send();
    }

    protected function calculateDiscountPreview(Forms\Set $set, Forms\Get $get, $state): void
    {
        // This triggers a re-render of the placeholder
    }

    /**
     * Check if the current practitioner can perform a service.
     */
    public function canPractitionerPerformService(int $serviceId): bool
    {
        $practitionerId = $this->appointment?->practitioner_id ?? auth()->id();

        if (! $practitionerId) {
            return false;
        }

        $service = Service::find($serviceId);
        if (! $service) {
            return false;
        }

        // Check if service has qualified staff restrictions
        $qualifiedStaffIds = $service->qualifiedStaff()
            ->with('user')
            ->get()
            ->pluck('user.id')
            ->filter()
            ->toArray();

        // If no qualified staff defined, any practitioner can perform
        if (empty($qualifiedStaffIds)) {
            return true;
        }

        return in_array($practitionerId, $qualifiedStaffIds);
    }

    /**
     * Open modal to start a new session for another service.
     */
    public function startSessionForItem(int $itemId): void
    {
        // Use withoutGlobalScope to avoid tenant scope issues - schema isolation handles tenancy
        $item = TreatmentPlanItem::withoutGlobalScope('tenant')->find($itemId);

        // Allow starting session if item exists, is a service, not completed, and not cancelled
        if (! $item || ! $item->isService() || $item->isCompleted() || $item->isCancelled()) {
            Notification::make()
                ->title(__('booking::session.messages.error'))
                ->body(__('booking::session.messages.cannot_start_session'))
                ->danger()
                ->send();

            return;
        }

        $this->pendingSessionItemId = $itemId;
        $this->mountAction('startAnotherSession');
    }

    /**
     * Get qualified practitioners for a service.
     */
    public function getQualifiedPractitionersForService(int $serviceId): array
    {
        $service = Service::find($serviceId);
        if (! $service) {
            return [];
        }

        $qualifiedStaff = $service->qualifiedStaff()
            ->with('user')
            ->get();

        // If no qualified staff defined, return empty (will use current practitioner)
        if ($qualifiedStaff->isEmpty()) {
            return [];
        }

        return $qualifiedStaff
            ->filter(fn ($staff) => $staff->user && $staff->user->id !== $this->appointment->practitioner_id)
            ->mapWithKeys(fn ($staff) => [$staff->user->id => $staff->user->full_name])
            ->toArray();
    }

    /**
     * Execute the start another session action.
     */
    public function executeStartAnotherSession(array $data): void
    {
        // Use withoutGlobalScope to avoid tenant scope issues - schema isolation handles tenancy
        $item = TreatmentPlanItem::withoutGlobalScope('tenant')->find($this->pendingSessionItemId);

        // Allow starting if item exists, is a service, not completed, and not cancelled
        if (! $item || ! $item->isService() || $item->isCompleted() || $item->isCancelled()) {
            Notification::make()
                ->title(__('booking::session.messages.error'))
                ->body(__('booking::session.messages.cannot_start_session'))
                ->danger()
                ->send();

            return;
        }

        $action = $data['action'] ?? 'keep_open';
        $practitionerId = $data['practitioner_id'] ?? $this->appointment->practitioner_id;

        // Check if there's already an active appointment for this treatment plan item
        $existingAppointment = Appointment::query()
            ->whereHas('treatmentPlanAppointment', function ($q) use ($item) {
                $q->where('treatment_plan_item_id', $item->id);
            })
            ->whereIn('status', [
                Appointment::STATUS_IN_PROGRESS,
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_SCHEDULED,
            ])
            ->where('id', '!=', $this->appointment->id)
            ->first();

        // If there's an existing active appointment, navigate to it instead of creating a new one
        if ($existingAppointment) {
            // Handle current session based on action
            if ($action === 'complete_current') {
                $this->completeSession();
            }

            // Start the existing appointment if not already in progress
            if ($existingAppointment->status === Appointment::STATUS_SCHEDULED) {
                $existingAppointment->confirm();
            }
            if ($existingAppointment->status === Appointment::STATUS_CONFIRMED) {
                $existingAppointment->checkIn();
            }
            if ($existingAppointment->status === Appointment::STATUS_CHECKED_IN) {
                $existingAppointment->start();
            }

            Notification::make()
                ->title(__('booking::session.messages.session_resumed'))
                ->body($item->service?->translated_name)
                ->success()
                ->send();

            $this->pendingSessionItemId = null;
            $this->redirect(static::getUrl(['appointment_id' => $existingAppointment->id]));

            return;
        }

        // Handle current session based on action
        if ($action === 'complete_current') {
            $this->completeSession();
        }

        // If assigning to another doctor, just create appointment without redirect
        $redirectToNewSession = ($action !== 'assign_doctor');

        // Create a new appointment for this service
        $now = now();
        $duration = $item->service?->duration_minutes ?? 30;

        $newAppointment = Appointment::create([
            'tenant_id' => $this->appointment->tenant_id,
            'branch_id' => $this->appointment->branch_id,
            'patient_id' => $this->appointment->patient_id,
            'service_id' => $item->service_id,
            'practitioner_id' => $practitionerId,
            'room_id' => $this->appointment->room_id,
            'date' => $now->toDateString(),
            'start_time' => $now->format('H:i:s'),
            'end_time' => $now->copy()->addMinutes($duration)->format('H:i:s'),
            'duration_minutes' => $duration,
            'price_minor' => $item->unit_price_minor, // Original price from plan item
            'discount_type' => 'fixed',
            'discount_minor' => $item->discount_minor ?? 0, // Discount from plan item
            'status' => Appointment::STATUS_SCHEDULED,
            'notes' => $item->notes,
            'source' => 'treatment_plan',
        ]);

        // Link appointment to treatment plan item
        $newAppointment->treatmentPlanAppointment()->create([
            'tenant_id' => $this->appointment->tenant_id,
            'treatment_plan_id' => $item->treatment_plan_id,
            'treatment_plan_item_id' => $item->id,
            'session_number' => $item->completed_sessions + 1,
        ]);

        // Update item status to in_progress when session is started
        if ($item->isPending()) {
            $item->status = TreatmentPlanItem::STATUS_IN_PROGRESS;
            $item->save();
        }

        // Link the new appointment to the current visit
        if ($this->visit) {
            app(VisitService::class)->addAppointment($this->visit, $newAppointment);
        }

        // Confirm, check in, and start if starting now
        if ($redirectToNewSession) {
            $newAppointment->confirm();
            // checkIn() will link to visit if not already linked
            $newAppointment->checkIn();
            $newAppointment->start(); // Set to IN_PROGRESS so TreatmentSession page accepts it

            Notification::make()
                ->title(__('booking::session.messages.session_started'))
                ->body($item->service?->translated_name)
                ->success()
                ->send();

            $this->redirect(static::getUrl(['appointment_id' => $newAppointment->id]));
        } else {
            // Just confirm for another doctor - they'll check in when ready
            $newAppointment->confirm();

            Notification::make()
                ->title(__('booking::session.messages.session_assigned'))
                ->body(__('booking::session.messages.session_assigned_body', [
                    'service' => $item->service?->translated_name,
                ]))
                ->success()
                ->send();
        }

        $this->pendingSessionItemId = null;
    }

    /**
     * Get current discount info for display.
     */
    public function getDiscountInfo(): array
    {
        if (! $this->appointment || ! $this->appointment->hasDiscount()) {
            return [
                'has_discount' => false,
            ];
        }

        return [
            'has_discount' => true,
            'type' => $this->appointment->discount_type,
            'value' => $this->appointment->discount_minor,
            'display' => $this->appointment->discount_display,
            'amount' => $this->appointment->getDiscountAmountMinor(),
            'reason' => $this->appointment->discount_reason,
            'original_price' => $this->appointment->price_minor,
            'net_price' => $this->appointment->net_price,
        ];
    }

    public function patientInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->patient)
            ->schema([
                Components\Grid::make(4)
                    ->schema([
                        Components\TextEntry::make('code')
                            ->label(__('booking::session.patient.code'))
                            ->copyable(),
                        Components\TextEntry::make('full_name')
                            ->label(__('booking::session.patient.name')),
                        Components\TextEntry::make('phone')
                            ->label(__('booking::session.patient.phone'))
                            ->copyable(),
                        Components\TextEntry::make('age')
                            ->label(__('booking::session.patient.age'))
                            ->suffix(' '.__('booking::session.patient.years')),
                    ]),
            ]);
    }

    public function medicalInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->medicalHistory)
            ->schema([
                Components\Grid::make(4)
                    ->schema([
                        Components\TextEntry::make('fitzpatrick_type')
                            ->label(__('booking::session.medical.fitzpatrick'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => PatientMedicalHistory::FITZPATRICK_TYPES[$state] ?? $state),
                        Components\TextEntry::make('blood_type')
                            ->label(__('booking::session.medical.blood_type')),
                        Components\TextEntry::make('bmi')
                            ->label(__('booking::session.medical.bmi')),
                        Components\TextEntry::make('is_smoker')
                            ->label(__('booking::session.medical.smoker'))
                            ->badge()
                            ->color(fn ($state) => $state ? 'danger' : 'success')
                            ->formatStateUsing(fn ($state) => $state ? __('Yes') : __('No')),
                    ]),
            ]);
    }

    public function getAllergies(): array
    {
        return $this->medicalHistory?->allergies ?? [];
    }

    public function getContraindications(): array
    {
        return $this->medicalHistory?->contraindications ?? [];
    }

    public function getMedications(): array
    {
        return $this->medicalHistory?->current_medications ?? [];
    }

    public function getMedicalConditions(): array
    {
        return $this->medicalHistory?->medical_conditions ?? [];
    }

    // ============================================
    // MEDICAL PROFILE METHODS
    // ============================================

    /**
     * Check if patient has a medical profile
     */
    public function hasMedicalProfile(): bool
    {
        return $this->medicalProfile !== null;
    }

    /**
     * Check if patient has medical alerts
     */
    public function hasMedicalAlerts(): bool
    {
        if (! $this->medicalHistory) {
            return false;
        }

        return ! empty($this->medicalHistory->allergies) ||
               ! empty($this->medicalHistory->contraindications) ||
               ! empty($this->medicalHistory->current_medications);
    }

    /**
     * Check if patient has AMR alerts (MDRO or resistances)
     */
    public function hasAmrAlerts(): bool
    {
        if (! $this->amrSummary) {
            return false;
        }

        return $this->amrSummary->has_any_data;
    }

    /**
     * Get AMR summary data for display
     */
    public function getAmrSummaryData(): array
    {
        if (! $this->amrSummary) {
            return [];
        }

        return [
            'has_mdro' => $this->amrSummary->has_mdro,
            'has_critical_resistance' => $this->amrSummary->has_critical_resistance,
            'mdro_flags' => $this->amrSummary->mdro_flags ?? [],
            'known_organisms' => $this->amrSummary->known_organisms ?? [],
            'known_resistances' => $this->amrSummary->getFormattedResistances(),
            'known_sensitivities' => $this->amrSummary->getFormattedSensitivities(),
            'last_test_date' => $this->amrSummary->last_test_date?->format('M d, Y'),
            'alert_message' => $this->amrSummary->getAlertMessage(),
            'alert_level' => $this->amrSummary->alert_level,
        ];
    }

    /**
     * Get MDRO flags for display
     */
    public function getMdroFlags(): array
    {
        if (! $this->amrSummary || empty($this->amrSummary->mdro_flags)) {
            return [];
        }

        return collect($this->amrSummary->mdro_flags)
            ->map(fn ($flag) => \Modules\Patients\Models\PatientAmrTest::MDRO_TYPES[$flag] ?? $flag)
            ->toArray();
    }

    /**
     * Get known resistances for display
     */
    public function getKnownResistances(): array
    {
        if (! $this->amrSummary || empty($this->amrSummary->known_resistances)) {
            return [];
        }

        return $this->amrSummary->getFormattedResistances();
    }

    public function getPatientNotes(): Collection
    {
        if (! $this->patient) {
            return collect();
        }

        return PatientNote::where('patient_id', $this->patient->id)
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Process camera photo after Livewire upload completes.
     */
    public function processCameraPhoto(): void
    {
        try {
            if (! $this->cameraPhoto) {
                return;
            }

            $extension = $this->cameraPhoto->getClientOriginalExtension() ?: 'jpg';
            $fileName = $this->getPhotoName().'.'.$extension;

            // Store to tenant disk
            $storedPath = $this->cameraPhoto->storeAs('patient-photos', $fileName, 'tenant');
            $fullPath = \Storage::disk('tenant')->path($storedPath);

            if (! file_exists($fullPath)) {
                throw new \Exception('Failed to store photo');
            }

            // Create the photo record
            $photo = PatientPhoto::create([
                'patient_id' => $this->patient->id,
                'appointment_id' => $this->appointment->id,
                'type' => $this->cameraPhotoType,
                'body_area' => null,
                'description' => $this->getPhotoDescription(),
                'taken_at' => now(),
                'taken_by' => auth()->id(),
            ]);

            // Add to media collection
            $photo->addMedia($fullPath)
                ->usingFileName($fileName)
                ->usingName($this->getPhotoName())
                ->toMediaCollection('photos');

            // Reset
            $this->cameraPhoto = null;

            Notification::make()
                ->title(__('booking::session.messages.photo_uploaded'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Log::error('Camera capture failed: '.$e->getMessage());

            Notification::make()
                ->title(__('booking::session.messages.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function uploadPhotoAction(): Action
    {
        return Action::make('uploadPhoto')
            ->label(__('booking::session.photos.choose_file'))
            ->icon('heroicon-o-photo')
            ->form([
                Forms\Components\FileUpload::make('photo')
                    ->label(__('booking::session.photos.take_photo'))
                    ->image()
                    ->maxSize(10240) // 10MB for camera photos
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                    ->disk('tenant')
                    ->directory('patient-photos')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label(__('booking::session.products.usage_type'))
                    ->options(PatientPhoto::TYPES)
                    ->default('progress')
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    $photoPath = $data['photo'];

                    if (empty($photoPath)) {
                        throw new \Exception('No photo uploaded');
                    }

                    // Get full path from tenant disk
                    $fullPath = \Storage::disk('tenant')->path($photoPath);

                    if (! file_exists($fullPath)) {
                        throw new \Exception('Uploaded file not found');
                    }

                    // Create the photo record
                    $photo = PatientPhoto::create([
                        'patient_id' => $this->patient->id,
                        'appointment_id' => $this->appointment->id,
                        'type' => $data['type'] ?? 'progress',
                        'body_area' => null,
                        'description' => $this->getPhotoDescription(),
                        'taken_at' => now(),
                        'taken_by' => auth()->id(),
                    ]);

                    // Add to media collection from tenant storage
                    $photo->addMedia($fullPath)
                        ->usingFileName($this->getPhotoName().'.'.pathinfo($fullPath, PATHINFO_EXTENSION))
                        ->usingName($this->getPhotoName())
                        ->toMediaCollection('photos');

                    Notification::make()
                        ->title(__('booking::session.messages.photo_uploaded'))
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    \Log::error('Photo upload failed: '.$e->getMessage());

                    Notification::make()
                        ->title(__('booking::session.messages.error'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public function getPatientPhotos(): Collection
    {
        if (! $this->patient) {
            return collect();
        }

        return PatientPhoto::where('patient_id', $this->patient->id)
            ->with('media')
            ->orderByDesc('taken_at')
            ->limit(12)
            ->get();
    }

    public function getActiveTreatmentPlans(): Collection
    {
        if (! $this->patient) {
            return collect();
        }

        return TreatmentPlan::query()
            ->forPatient($this->patient->id)
            ->active()
            ->with('items.service')
            ->get();
    }

    public function getCurrentTreatmentPlan(): ?TreatmentPlan
    {
        if (! $this->appointment?->treatmentPlanAppointment) {
            return null;
        }

        return $this->appointment->treatmentPlanAppointment->item->treatmentPlan;
    }

    // Track if photo upload is ready
    public bool $photoUploadReady = false;

    // Called when photoUpload property is updated by Livewire
    public function updatedPhotoUpload($value): void
    {
        \Log::info('=== updatedPhotoUpload HOOK TRIGGERED ===', [
            'hasValue' => ! empty($value),
            'valueType' => $value ? get_class($value) : 'null',
            'photoUploadProp' => ! empty($this->photoUpload) ? get_class($this->photoUpload) : 'null',
        ]);

        if ($this->photoUpload) {
            $this->photoUploadReady = true;

            \Log::info('Photo upload ready - dispatching event', [
                'fileName' => $this->photoUpload->getClientOriginalName(),
                'size' => $this->photoUpload->getSize(),
                'mimeType' => $this->photoUpload->getMimeType(),
            ]);

            $this->dispatch('photo-ready');
        }
    }

    public function addNote(): void
    {
        if (empty($this->noteContent)) {
            Notification::make()
                ->title(__('booking::session.messages.note_required'))
                ->warning()
                ->send();

            return;
        }

        PatientNote::create([
            'patient_id' => $this->patient->id,
            'appointment_id' => $this->appointment->id,
            'type' => $this->noteType,
            'subject' => __('booking::session.notes.session_note').' - '.$this->appointment->service?->translated_name,
            'content' => $this->noteContent,
            'created_by' => auth()->id(),
        ]);

        $this->noteContent = null;

        Notification::make()
            ->title(__('booking::session.messages.note_added'))
            ->success()
            ->send();
    }

    public function uploadPhoto(): void
    {
        \Log::info('uploadPhoto called', [
            'hasFile' => ! empty($this->photoUpload),
            'fileType' => $this->photoUpload ? get_class($this->photoUpload) : null,
        ]);

        if (! $this->photoUpload) {
            Notification::make()
                ->title(__('booking::session.messages.photo_required'))
                ->warning()
                ->send();

            return;
        }

        try {
            // Get the original extension
            $extension = $this->photoUpload->getClientOriginalExtension() ?: 'jpg';
            $fileName = $this->getPhotoName().'.'.$extension;

            \Log::info('Storing temp file', ['fileName' => $fileName, 'extension' => $extension]);

            // Store the uploaded file temporarily
            $tempPath = $this->photoUpload->storeAs('temp-photos', $fileName, 'local');
            $fullPath = storage_path('app/'.$tempPath);

            \Log::info('Temp file stored', ['tempPath' => $tempPath, 'fullPath' => $fullPath, 'exists' => file_exists($fullPath)]);

            // Create the photo record
            $photo = PatientPhoto::create([
                'patient_id' => $this->patient->id,
                'appointment_id' => $this->appointment->id,
                'type' => $this->photoType,
                'body_area' => $this->photoBodyArea,
                'description' => $this->photoDescription ?? $this->getPhotoDescription(),
                'taken_at' => now(),
                'taken_by' => auth()->id(),
            ]);

            \Log::info('PatientPhoto created', ['photo_id' => $photo->id]);

            // Add to media collection
            $photo->addMedia($fullPath)
                ->usingFileName($fileName)
                ->usingName($this->getPhotoName())
                ->toMediaCollection('photos');

            \Log::info('Media added to collection', ['media_count' => $photo->getMedia('photos')->count()]);

            // Reset form
            $this->photoUpload = null;
            $this->photoDescription = null;
            $this->dispatch('photo-uploaded');

            Notification::make()
                ->title(__('booking::session.messages.photo_uploaded'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Log::error('Photo upload failed: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'patient_id' => $this->patient?->id,
                'appointment_id' => $this->appointment?->id,
            ]);

            Notification::make()
                ->title(__('booking::session.messages.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getPhotoDescription(): string
    {
        $sessionNum = '';
        if ($planAppointment = $this->appointment?->treatmentPlanAppointment) {
            $sessionNum = ' - Session '.$planAppointment->session_number;
        }

        return $this->appointment?->service?->translated_name.$sessionNum.' - '.now()->format('M d, Y');
    }

    protected function getPhotoName(): string
    {
        return 'patient_'.($this->patient?->id ?? 'unknown').'_'.now()->format('Y-m-d_His');
    }

    public function deletePhoto(string $photoId): void
    {
        $photo = PatientPhoto::find($photoId);

        // Only allow deleting photos from current appointment
        if ($photo && $photo->appointment_id === $this->appointment?->id) {
            $photo->clearMediaCollection('photos');
            $photo->delete();

            Notification::make()
                ->title(__('booking::session.messages.photo_deleted'))
                ->success()
                ->send();
        }
    }

    public function createTreatmentPlan(): void
    {
        $this->validate([
            'treatmentPlanData.name' => 'required|string|max:255',
            'treatmentPlanData.services' => 'required|array|min:1',
            'treatmentPlanData.services.*.service_id' => 'required|exists:services,id',
            'treatmentPlanData.services.*.sessions' => 'required|integer|min:1|max:100',
            'treatmentPlanData.services.*.interval' => 'required|integer|min:1|max:365',
        ]);

        try {
            DB::transaction(function () {
                $plan = TreatmentPlan::create([
                    'patient_id' => $this->patient->id,
                    'branch_id' => $this->appointment->branch_id,
                    'created_by_user_id' => auth()->id(),
                    'name' => ['en' => $this->treatmentPlanData['name'], 'ar' => $this->treatmentPlanData['name']],
                    'status' => TreatmentPlan::STATUS_ACTIVE,
                    'source' => TreatmentPlan::SOURCE_CONSULTATION,
                    'start_date' => today(),
                    'notes' => $this->treatmentPlanData['notes'] ?? null,
                ]);

                foreach ($this->treatmentPlanData['services'] as $index => $serviceData) {
                    $service = Service::find($serviceData['service_id']);

                    TreatmentPlanItem::create([
                        'tenant_id' => $plan->tenant_id,
                        'treatment_plan_id' => $plan->id,
                        'service_id' => $serviceData['service_id'],
                        'item_type' => TreatmentPlanItem::TYPE_SERVICE,
                        'recommended_sessions' => $serviceData['sessions'],
                        'session_interval_days' => $serviceData['interval'],
                        'unit_price_minor' => $service?->base_price_minor ?? 0,
                        'discount_minor' => 0,
                        'sort_order' => $index,
                    ]);
                }

                $plan->recalculateFinancials();
            });

            $this->treatmentPlanData = [
                'name' => '',
                'services' => [['service_id' => null, 'sessions' => 1, 'interval' => 7]],
                'notes' => '',
            ];

            Notification::make()
                ->title(__('booking::session.messages.plan_created'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::session.messages.plan_creation_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addServiceRow(): void
    {
        $this->treatmentPlanData['services'][] = [
            'service_id' => null,
            'sessions' => 1,
            'interval' => 7,
        ];
    }

    public function removeServiceRow(int $index): void
    {
        if (count($this->treatmentPlanData['services']) > 1) {
            unset($this->treatmentPlanData['services'][$index]);
            $this->treatmentPlanData['services'] = array_values($this->treatmentPlanData['services']);
        }
    }

    public function getAvailableServices(): array
    {
        return Service::query()
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->translated_name])
            ->toArray();
    }

    public function getPreviousAppointments(): Collection
    {
        if (! $this->patient) {
            return collect();
        }

        return Appointment::where('patient_id', $this->patient->id)
            ->where('id', '!=', $this->appointment->id)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->with(['service', 'practitioner'])
            ->orderByDesc('date')
            ->limit(5)
            ->get();
    }

    // ============================================
    // DYNAMIC PARAMETERS METHODS
    // ============================================

    public function getServiceParameters(): array
    {
        if (! $this->appointment?->service) {
            return [];
        }

        return $this->appointment->service->getParameterDefinitions();
    }

    public function hasServiceParameters(): bool
    {
        return $this->appointment?->service?->hasParameters() ?? false;
    }

    /**
     * Check if the equipment section should be shown.
     * Shows if there's session equipment, available equipment, or service has parameters.
     */
    public function hasEquipmentSection(): bool
    {
        // Show if we have session equipment
        if (! empty($this->sessionEquipment)) {
            return true;
        }

        // Show if there's available equipment to add
        if ($this->getAvailableEquipment()->isNotEmpty()) {
            return true;
        }

        // Show if service has effective equipment (from service or category)
        if ($this->appointment?->service?->getEffectiveEquipment()->isNotEmpty()) {
            return true;
        }

        // Also show if service has parameters (for backward compatibility)
        return $this->hasServiceParameters();
    }

    public function updateParameterValue(string $key, $value): void
    {
        $this->parameterValues[$key] = $value;
        $this->saveParameterValues();
    }

    public function saveParameterValues(): void
    {
        if (! $this->sessionData) {
            return;
        }

        $this->sessionData->update([
            'parameter_values' => $this->parameterValues,
        ]);
    }

    // ============================================
    // EQUIPMENT METHODS
    // ============================================

    protected function loadSessionEquipment(): void
    {
        if (! $this->appointment) {
            return;
        }

        // Load from session data if available
        $savedEquipment = $this->sessionData->session_equipment ?? [];
        $savedParameterValues = $this->sessionData->equipment_parameter_values ?? [];

        if (! empty($savedEquipment)) {
            // Refresh equipment data to get latest shot counts
            $this->sessionEquipment = $this->refreshEquipmentData($savedEquipment);
            $this->equipmentParameterValues = $savedParameterValues;

            return;
        }

        // Auto-load equipment using override pattern (service first, then category)
        $serviceEquipment = [];
        if ($this->appointment->service) {
            // Use getEffectiveEquipment for override pattern
            $effectiveEquipment = $this->appointment->service->getEffectiveEquipment();

            // Filter by status and branch (include equipment with no branch or matching branch)
            $branchId = $this->appointment->branch_id;
            $requiredEquipment = $effectiveEquipment
                ->filter(function ($equipment) use ($branchId) {
                    // Must be active
                    if ($equipment->status !== Equipment::STATUS_ACTIVE) {
                        return false;
                    }

                    // Include if: no branch set, or matches appointment branch
                    return empty($equipment->branch_id) || $equipment->branch_id == $branchId;
                });

            foreach ($requiredEquipment as $equipment) {
                $equipment->load('trackingParameters');
                $serviceEquipment[] = $this->buildEquipmentData($equipment, true);

                // Initialize parameter values with defaults
                if ($equipment->hasTracking()) {
                    $this->equipmentParameterValues[(int) $equipment->id] = $equipment->getDefaultParameterValues();
                }
            }
        }

        // Also add equipment from appointment if set
        if ($this->appointment->equipment_id && ! collect($serviceEquipment)->pluck('equipment_id')->contains($this->appointment->equipment_id)) {
            $equipment = Equipment::with('trackingParameters')->find($this->appointment->equipment_id);
            if ($equipment) {
                $serviceEquipment[] = $this->buildEquipmentData($equipment, true);

                // Initialize parameter values with defaults
                if ($equipment->hasTracking()) {
                    $this->equipmentParameterValues[(int) $equipment->id] = $equipment->getDefaultParameterValues();
                }
            }
        }

        $this->sessionEquipment = $serviceEquipment;

        // Save to session data
        if ($this->sessionData && ! empty($serviceEquipment)) {
            $this->sessionData->update([
                'session_equipment' => $serviceEquipment,
                'equipment_parameter_values' => $this->equipmentParameterValues,
            ]);
        }
    }

    /**
     * Build equipment data array with tracking info.
     */
    protected function buildEquipmentData(Equipment $equipment, bool $isPreset = false): array
    {
        return [
            'id' => $equipment->id,
            'equipment_id' => $equipment->id,
            'name' => $equipment->name,
            'code' => $equipment->code,
            'category' => $equipment->category,
            'is_preset' => $isPreset,
            'has_tracking' => $equipment->hasTracking(),
            // Shot tracking data (from equipment totals)
            'max_shots' => $equipment->max_shots,
            'total_shots_fired' => $equipment->total_shots_fired,
            'shots_remaining' => $equipment->shots_remaining,
            'shots_percentage' => $equipment->shots_percentage,
            // Maintenance data
            'is_maintenance_due' => $equipment->is_maintenance_due,
            'next_maintenance_at' => $equipment->next_maintenance_at?->format('Y-m-d'),
            'last_maintenance_at' => $equipment->last_maintenance_at?->format('Y-m-d'),
            // Status
            'status' => $equipment->status,
        ];
    }

    /**
     * Refresh equipment data to get latest shot counts and tracking info.
     */
    protected function refreshEquipmentData(array $savedEquipment): array
    {
        $equipmentIds = collect($savedEquipment)->pluck('equipment_id')->toArray();
        $freshEquipment = Equipment::with('trackingParameters')
            ->whereIn('id', $equipmentIds)
            ->get()
            ->keyBy('id');

        return collect($savedEquipment)->map(function ($item) use ($freshEquipment) {
            $equipment = $freshEquipment->get($item['equipment_id']);
            if ($equipment) {
                // Update tracking data but preserve session-specific data
                $item['max_shots'] = $equipment->max_shots;
                $item['total_shots_fired'] = $equipment->total_shots_fired;
                $item['shots_remaining'] = $equipment->shots_remaining;
                $item['shots_percentage'] = $equipment->shots_percentage;
                $item['is_maintenance_due'] = $equipment->is_maintenance_due;
                $item['next_maintenance_at'] = $equipment->next_maintenance_at?->format('Y-m-d');
                $item['status'] = $equipment->status;
                // Refresh has_tracking in case parameters were added/removed
                $item['has_tracking'] = $equipment->hasTracking();
            }

            return $item;
        })->toArray();
    }

    /**
     * Get equipment info for display (refreshed from DB).
     */
    public function getEquipmentInfo(string $equipmentId): ?array
    {
        $equipment = Equipment::find($equipmentId);
        if (! $equipment) {
            return null;
        }

        return [
            'id' => $equipment->id,
            'name' => $equipment->name,
            'code' => $equipment->code,
            'category' => $equipment->category,
            'category_label' => Equipment::CATEGORIES[$equipment->category] ?? $equipment->category,
            'max_shots' => $equipment->max_shots,
            'total_shots_fired' => $equipment->total_shots_fired,
            'shots_remaining' => $equipment->shots_remaining,
            'shots_percentage' => $equipment->shots_percentage,
            'is_maintenance_due' => $equipment->is_maintenance_due,
            'next_maintenance_at' => $equipment->next_maintenance_at?->format('M d, Y'),
            'last_maintenance_at' => $equipment->last_maintenance_at?->format('M d, Y'),
            'status' => $equipment->status,
            'status_label' => Equipment::STATUSES[$equipment->status] ?? $equipment->status,
            'status_color' => Equipment::STATUS_COLORS[$equipment->status] ?? 'gray',
        ];
    }

    public function getAvailableEquipment(): Collection
    {
        if (! $this->appointment) {
            return collect();
        }

        // Get equipment IDs already in session
        $usedEquipmentIds = collect($this->sessionEquipment)->pluck('equipment_id')->toArray();

        $branchId = $this->appointment->branch_id;

        return Equipment::query()
            ->where('status', Equipment::STATUS_ACTIVE)
            ->where(function ($query) use ($branchId) {
                // Include equipment with no branch or matching branch
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
            })
            ->whereNotIn('id', $usedEquipmentIds)
            ->orderBy('name')
            ->get();
    }

    public function addEquipment(): void
    {
        if (! $this->newEquipmentId) {
            return;
        }

        $equipment = Equipment::with('trackingParameters')->find($this->newEquipmentId);
        if (! $equipment) {
            return;
        }

        // Check if already exists
        if (collect($this->sessionEquipment)->pluck('equipment_id')->contains($this->newEquipmentId)) {
            Notification::make()
                ->title(__('booking::session.equipment.already_added'))
                ->warning()
                ->send();

            return;
        }

        $this->sessionEquipment[] = $this->buildEquipmentData($equipment, false);

        // Initialize parameter values with defaults if equipment has tracking
        if ($equipment->hasTracking()) {
            $this->equipmentParameterValues[(int) $equipment->id] = $equipment->getDefaultParameterValues();
        }

        // Save to session data
        if ($this->sessionData) {
            $this->sessionData->update([
                'session_equipment' => $this->sessionEquipment,
                'equipment_parameter_values' => $this->equipmentParameterValues,
            ]);
        }

        $this->newEquipmentId = null;

        $this->dispatch('equipment-added');

        Notification::make()
            ->title(__('booking::session.equipment.added'))
            ->success()
            ->send();
    }

    public function removeEquipment(string $equipmentId): void
    {
        $this->sessionEquipment = array_values(array_filter(
            $this->sessionEquipment,
            fn ($e) => (string) $e['equipment_id'] !== $equipmentId
        ));

        // Remove parameter values for this equipment
        unset($this->equipmentParameterValues[$equipmentId]);
        unset($this->equipmentParameterValues[(int) $equipmentId]);

        // Save to session data
        if ($this->sessionData) {
            $this->sessionData->update([
                'session_equipment' => $this->sessionEquipment,
                'equipment_parameter_values' => $this->equipmentParameterValues,
            ]);
        }
    }

    public function updateEquipmentMetric(string $equipmentId, string $key, $value): void
    {
        foreach ($this->sessionEquipment as $index => $equipment) {
            if ((string) $equipment['equipment_id'] === $equipmentId) {
                $this->sessionEquipment[$index][$key] = $value;
                break;
            }
        }

        // Save to session data
        if ($this->sessionData) {
            $this->sessionData->update([
                'session_equipment' => $this->sessionEquipment,
            ]);
        }
    }

    /**
     * Get tracking parameters for a specific equipment.
     */
    public function getEquipmentTrackingParameters(string $equipmentId): array
    {
        $equipment = Equipment::with('trackingParameters')->find($equipmentId);

        if (! $equipment || ! $equipment->hasTracking()) {
            return [];
        }

        return $equipment->getSessionTrackingParameters()
            ->map(fn ($param) => $param->toFormFieldConfig())
            ->toArray();
    }

    /**
     * Get equipment tracking parameters grouped by category.
     */
    public function getEquipmentParametersByCategory(string $equipmentId): array
    {
        $equipment = Equipment::with('trackingParameters')->find($equipmentId);

        if (! $equipment || ! $equipment->hasTracking()) {
            return [];
        }

        return $equipment->getTrackingParametersByCategory();
    }

    /**
     * Update an equipment parameter value.
     */
    public function updateEquipmentParameterValue(string $equipmentId, string $key, $value): void
    {
        // Normalize key to int for consistency
        $normalizedId = (int) $equipmentId;

        if (! isset($this->equipmentParameterValues[$normalizedId])) {
            $this->equipmentParameterValues[$normalizedId] = [];
        }

        $this->equipmentParameterValues[$normalizedId][$key] = $value;

        // Save to session data
        if ($this->sessionData) {
            $this->sessionData->update([
                'equipment_parameter_values' => $this->equipmentParameterValues,
            ]);
        }
    }

    /**
     * Get a specific equipment parameter value.
     */
    public function getEquipmentParameterValue(string $equipmentId, string $key)
    {
        // Check both string and int keys for backwards compatibility
        return $this->equipmentParameterValues[$equipmentId][$key]
            ?? $this->equipmentParameterValues[(int) $equipmentId][$key]
            ?? null;
    }

    /**
     * Check if any equipment has tracking parameters.
     */
    public function hasEquipmentWithTracking(): bool
    {
        foreach ($this->sessionEquipment as $equipment) {
            if (! empty($equipment['has_tracking'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update cumulative equipment parameters on session complete.
     * This handles dynamic parameters like shots, energy, pulses, etc.
     */
    protected function updateCumulativeEquipmentParameters(): void
    {
        foreach ($this->sessionEquipment as $equipmentData) {
            $equipmentId = $equipmentData['equipment_id'];
            $equipment = Equipment::with('trackingParameters')->find($equipmentId);

            if (! $equipment) {
                continue;
            }

            // Handle both string and int keys for backwards compatibility
            $parameterValues = $this->equipmentParameterValues[$equipmentId]
                ?? $this->equipmentParameterValues[(int) $equipmentId]
                ?? [];

            // Get cumulative parameters for this equipment
            $cumulativeParams = $equipment->trackingParameters()
                ->where('is_cumulative', true)
                ->where('is_active', true)
                ->get();

            $cumulativeData = [];

            foreach ($cumulativeParams as $param) {
                $value = $parameterValues[$param->parameter_key] ?? null;

                if ($value !== null && is_numeric($value) && $value > 0) {
                    $cumulativeData[$param->parameter_key] = [
                        'value' => (float) $value,
                        'unit' => $param->unit,
                        'name' => $param->name,
                    ];

                    // Special handling for shots - update total_shots_fired
                    if (in_array($param->parameter_key, ['shots', 'shots_used', 'pulses', 'pulse_count'])) {
                        $equipment->recordShots(
                            (int) $value,
                            $this->appointment->id,
                            $parameterValues
                        );
                    }
                }
            }

            // Log cumulative data to equipment shot log for tracking
            if (! empty($cumulativeData)) {
                $equipment->shotLogs()->create([
                    'appointment_id' => $this->appointment->id,
                    'shots_count' => $cumulativeData['shots']['value'] ?? $cumulativeData['shots_used']['value'] ?? $cumulativeData['pulses']['value'] ?? 0,
                    'energy_setting' => $parameterValues['energy'] ?? $parameterValues['fluence'] ?? $parameterValues['energy_setting'] ?? null,
                    'spot_size' => $parameterValues['spot_size'] ?? null,
                    'pulse_duration' => $parameterValues['pulse_duration'] ?? $parameterValues['pulse_width'] ?? null,
                    'cumulative_data' => $cumulativeData,
                    'all_parameters' => $parameterValues,
                    'logged_at' => now(),
                ]);
            }
        }
    }

    // ============================================
    // PRESET METHODS
    // ============================================

    public function getAvailablePresets(): Collection
    {
        if (! $this->appointment?->service_id) {
            return collect();
        }

        return ParameterPreset::query()
            ->where('service_id', $this->appointment->service_id)
            ->where('is_active', true)
            ->get();
    }

    public function applyPreset(string $presetId): void
    {
        $preset = ParameterPreset::find($presetId);
        if (! $preset) {
            return;
        }

        $this->selectedPresetId = $presetId;
        $this->parameterValues = array_merge($this->parameterValues, $preset->getValues());

        if ($this->sessionData) {
            $this->sessionData->update([
                'preset_id' => $presetId,
                'parameter_values' => $this->parameterValues,
            ]);
        }

        Notification::make()
            ->title(__('booking::session.messages.preset_applied'))
            ->success()
            ->send();
    }

    // ============================================
    // CHECKLIST METHODS
    // ============================================

    public function updateChecklistItem(string $key, bool $value): void
    {
        $this->preTreatmentChecklist[$key] = $value;

        if ($this->sessionData) {
            $this->sessionData->update([
                'pre_treatment_checklist' => $this->preTreatmentChecklist,
            ]);
        }
    }

    public function isChecklistComplete(): bool
    {
        foreach ($this->preTreatmentChecklist as $value) {
            if (! $value) {
                return false;
            }
        }

        return true;
    }

    public function getChecklistProgress(): array
    {
        $total = count($this->preTreatmentChecklist);
        $completed = count(array_filter($this->preTreatmentChecklist));

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    // ============================================
    // CLINICAL NOTES METHODS
    // ============================================

    public function saveClinicalNotes(): void
    {
        if (! $this->sessionData) {
            return;
        }

        $this->sessionData->update([
            'clinical_notes' => $this->clinicalNotes,
            'skin_reaction' => $this->skinReaction,
            'pain_level' => $this->painLevel,
        ]);

        Notification::make()
            ->title(__('booking::session.messages.clinical_notes_saved'))
            ->success()
            ->send();
    }

    // ============================================
    // TREATMENT AREAS METHODS
    // ============================================

    public function addTreatmentArea(array $area): void
    {
        $this->treatmentAreas[] = $area;

        if ($this->sessionData) {
            $this->sessionData->update([
                'treatment_areas' => $this->treatmentAreas,
            ]);
        }
    }

    public function removeTreatmentArea(int $index): void
    {
        unset($this->treatmentAreas[$index]);
        $this->treatmentAreas = array_values($this->treatmentAreas);

        if ($this->sessionData) {
            $this->sessionData->update([
                'treatment_areas' => $this->treatmentAreas,
            ]);
        }
    }

    public function getTotalPulses(): int
    {
        return array_sum(array_column($this->treatmentAreas, 'pulses'));
    }

    // ============================================
    // SKIN REACTION OPTIONS
    // ============================================

    public function getSkinReactionOptions(): array
    {
        return TreatmentSessionData::SKIN_REACTIONS;
    }

    // ============================================
    // CONSUMABLES METHODS
    // ============================================

    public function getAvailableConsumables(): Collection
    {
        $branchId = $this->appointment?->branch_id;

        // Use the treatment default location (is_treatment_default flag)
        $stockLocation = $branchId
            ? StockLocation::getTreatmentDefaultLocation($branchId)
            : null;

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_consumable', true)
            ->with(['salesUom', 'category'])
            ->get();

        // Add stock quantity to each product
        $stockLevels = [];
        if ($stockLocation) {
            $stockLevels = StockLevel::where('location_id', $stockLocation->id)
                ->pluck('quantity_on_hand', 'product_id')
                ->toArray();
        }

        $products->each(function ($product) use ($stockLevels) {
            $product->stock_qty = $stockLevels[$product->id] ?? 0;
            $product->stock_uom = $product->salesUom?->abbreviation ?? 'pcs';
        });

        // Filter out products that are out of stock and don't allow negative stock
        return $products->filter(function ($product) {
            // If product has stock, always show it
            if ($product->stock_qty > 0) {
                return true;
            }

            // If out of stock, only show if category allows negative stock
            return $product->category?->allow_negative_stock ?? false;
        })->values();
    }

    /**
     * Reload session consumables list + markers count from DB.
     * Called after the face-chart marker wizard writes a new SessionConsumable
     * so the session page reflects the change without a page reload.
     */
    #[On('sessionConsumablesRefresh')]
    public function reloadSessionConsumables(): void
    {
        if (! $this->appointment) {
            return;
        }

        $existingConsumables = SessionConsumable::where('appointment_id', $this->appointment->id)
            ->with('product')
            ->withCount('faceChartMarkers')
            ->get();

        $this->sessionConsumables = $existingConsumables
            ->map(fn ($c) => [
                'id' => $c->id,
                'product_id' => $c->product_id,
                'product_name' => $c->product?->getTranslation('name', app()->getLocale()) ?? '',
                'quantity' => $c->quantity,
                'base_quantity' => $c->base_quantity ?? $c->quantity,
                'unit' => $c->unit_abbreviation,
                'unit_cost' => $c->unit_cost,
                'total_cost' => $c->total_cost,
                'markers_count' => $c->face_chart_markers_count ?? 0,
            ])
            ->toArray();
    }

    public function addConsumable(): void
    {
        if (! $this->newConsumableId || ! $this->appointment) {
            return;
        }

        $product = Product::find($this->newConsumableId);
        if (! $product) {
            return;
        }

        $enteredQty = (float) ($this->newConsumableQty ?? 1);

        // Merge with existing row for same (appointment_id, product_id) if present
        $consumable = SessionConsumable::firstOrNew([
            'appointment_id' => $this->appointment->id,
            'product_id' => $product->id,
        ]);

        $wasMerged = $consumable->exists;
        $newQty = (float) ($consumable->quantity ?? 0) + $enteredQty;
        $serviceQty = (float) ($this->appointment->quantity ?? 1);
        $newBaseQty = $serviceQty > 0 ? $newQty / $serviceQty : $newQty;

        if (! $wasMerged) {
            $consumable->tenant_id = $this->appointment->tenant_id;
            $consumable->branch_id = $this->appointment->branch_id;
            $consumable->uom_id = $product->sales_uom_id;
            $consumable->unit = $product->unit_abbreviation;
            $consumable->unit_cost_minor = $product->cost_price_minor;
            $consumable->created_by = auth()->id();
        }

        $consumable->quantity = $newQty;
        $consumable->base_quantity = $newBaseQty;
        $consumable->save();

        // Reflect in in-memory list: update existing entry if merged, otherwise push
        $productName = $product->getTranslation('name', app()->getLocale());
        $foundIndex = null;
        foreach ($this->sessionConsumables as $idx => $row) {
            if ((int) ($row['product_id'] ?? 0) === (int) $product->id) {
                $foundIndex = $idx;
                break;
            }
        }

        $rowData = [
            'id' => $consumable->id,
            'product_id' => $consumable->product_id,
            'product_name' => $productName,
            'quantity' => $consumable->quantity,
            'base_quantity' => $consumable->base_quantity,
            'unit' => $consumable->unit_abbreviation,
            'unit_cost' => $consumable->unit_cost,
            'total_cost' => $consumable->total_cost,
            'markers_count' => $consumable->faceChartMarkers()->count(),
        ];

        if ($foundIndex !== null) {
            $this->sessionConsumables[$foundIndex] = $rowData;
        } else {
            $this->sessionConsumables[] = $rowData;
        }

        $this->newConsumableId = null;
        $this->newConsumableQty = 1;

        $this->dispatch('consumable-added');

        $unitLabel = $consumable->unit_abbreviation;
        Notification::make()
            ->title(
                $wasMerged
                    ? __('booking::session.messages.consumable_merged', [
                        'qty' => rtrim(rtrim(number_format($enteredQty, 2), '0'), '.'),
                        'unit' => $unitLabel,
                        'product' => $productName,
                    ])
                    : __('booking::session.messages.consumable_added')
            )
            ->success()
            ->send();
    }

    public function removeConsumable(string $consumableId): void
    {
        $consumable = SessionConsumable::find($consumableId);
        if ($consumable) {
            $consumable->delete();
        }

        $this->sessionConsumables = array_values(
            array_filter($this->sessionConsumables, fn ($c) => (string) $c['id'] !== $consumableId)
        );

        // Refresh face chart markers since linked ones were removed
        $this->dispatch('faceChartMarkersRefresh');

        Notification::make()
            ->title(__('booking::session.messages.consumable_removed'))
            ->success()
            ->send();
    }

    public function getTotalConsumablesCost(): float
    {
        return array_sum(array_column($this->sessionConsumables, 'total_cost'));
    }

    // ============================================
    // PRODUCTS METHODS
    // ============================================

    public function getAvailableProducts(): Collection
    {
        $branchId = $this->appointment?->branch_id;

        // Use the treatment default location (is_treatment_default flag)
        // This is the store location for selling products to patients
        $stockLocation = $branchId
            ? StockLocation::getTreatmentDefaultLocation($branchId)
            : null;

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_consumable', false) // Only sellable products, not consumables
            ->with(['salesUom', 'category'])
            ->get();

        // Add stock quantity to each product
        $stockLevels = [];
        if ($stockLocation) {
            $stockLevels = StockLevel::where('location_id', $stockLocation->id)
                ->pluck('quantity_on_hand', 'product_id')
                ->toArray();
        }

        $products->each(function ($product) use ($stockLevels) {
            $product->stock_qty = $stockLevels[$product->id] ?? 0;
            $product->stock_uom = $product->salesUom?->abbreviation ?? 'pcs';
        });

        // Filter out products that are out of stock and don't allow negative stock
        return $products->filter(function ($product) {
            // If product has stock, always show it
            if ($product->stock_qty > 0) {
                return true;
            }

            // If out of stock, only show if category allows negative stock
            return $product->category?->allow_negative_stock ?? false;
        })->values();
    }

    public function addProduct(): void
    {
        if (! $this->newProductId || ! $this->appointment) {
            return;
        }

        $product = Product::find($this->newProductId);
        if (! $product) {
            return;
        }

        $sessionProduct = SessionProduct::create([
            'tenant_id' => $this->appointment->tenant_id,
            'appointment_id' => $this->appointment->id,
            'product_id' => $product->id,
            'branch_id' => $this->appointment->branch_id,
            'visit_id' => $this->visit?->id,
            'quantity' => $this->newProductQty ?? 1,
            'uom_id' => $product->sales_uom_id,
            'unit' => $product->unit_abbreviation, // Fallback for display
            'unit_price_minor' => $product->sell_price_minor,
            'usage_type' => 'sold',
            'created_by' => auth()->id(),
        ]);

        $this->sessionProducts[] = [
            'id' => $sessionProduct->id,
            'product_id' => $sessionProduct->product_id,
            'product_name' => $product->getTranslation('name', app()->getLocale()),
            'quantity' => $sessionProduct->quantity,
            'unit' => $sessionProduct->unit_abbreviation,
            'unit_price' => $sessionProduct->unit_price,
            'total_price' => $sessionProduct->total_price,
            'usage_type' => 'sold',
            'discount_type' => 'none',
            'discount_value' => 0,
        ];

        $this->newProductId = null;
        $this->newProductQty = 1;

        $this->dispatch('product-added');

        Notification::make()
            ->title(__('booking::session.messages.product_added'))
            ->success()
            ->send();
    }

    public function removeProduct(string $productId): void
    {
        SessionProduct::where('id', $productId)->delete();

        $this->sessionProducts = array_values(
            array_filter($this->sessionProducts, fn ($p) => (string) $p['id'] !== $productId)
        );

        Notification::make()
            ->title(__('booking::session.messages.product_removed'))
            ->success()
            ->send();
    }

    public function getTotalProductsValue(): float
    {
        return array_sum(array_column($this->sessionProducts, 'total_price'));
    }

    public function getProductUsageTypes(): array
    {
        return SessionProduct::USAGE_TYPES;
    }

    // ============================================
    // PRESCRIPTION METHODS
    // ============================================

    /**
     * Get existing prescriptions for this appointment.
     */
    public function getAppointmentPrescriptions(): \Illuminate\Support\Collection
    {
        if (! $this->appointment) {
            return collect();
        }

        return \Modules\Prescriptions\Models\Prescription::query()
            ->where('appointment_id', $this->appointment->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Add a new medication row to the prescription form.
     */
    public function addPrescriptionMedication(): void
    {
        $this->prescriptionMedications[] = [
            'medication_name' => '',
            'generic_name' => '',
            'dosage' => '',
            'dosage_unit' => 'mg',
            'form' => 'tablet',
            'frequency' => 'twice_daily',
            'duration' => '',
            'duration_unit' => 'days',
            'quantity' => '',
            'route' => 'oral',
            'instructions' => '',
            'special_instructions' => '',
        ];
    }

    /**
     * Remove a medication row from the prescription form.
     */
    public function removePrescriptionMedication(int $index): void
    {
        if (isset($this->prescriptionMedications[$index])) {
            unset($this->prescriptionMedications[$index]);
            $this->prescriptionMedications = array_values($this->prescriptionMedications);
        }
    }

    /**
     * Save prescription as draft.
     */
    public function savePrescriptionDraft(): void
    {
        if (empty($this->prescriptionMedications)) {
            Notification::make()
                ->title(__('prescriptions::prescription.messages.no_medications'))
                ->warning()
                ->send();

            return;
        }

        $this->createPrescription(false);
    }

    /**
     * Finalize and optionally print prescription.
     */
    public function finalizePrescription(): void
    {
        if (empty($this->prescriptionMedications)) {
            Notification::make()
                ->title(__('prescriptions::prescription.messages.no_medications'))
                ->warning()
                ->send();

            return;
        }

        $prescription = $this->createPrescription(true);

        if ($prescription) {
            Notification::make()
                ->title(__('prescriptions::prescription.messages.finalized'))
                ->success()
                ->send();
        }
    }

    /**
     * Create a prescription with medications.
     */
    protected function createPrescription(bool $finalize): ?\Modules\Prescriptions\Models\Prescription
    {
        if (! $this->appointment || empty($this->prescriptionMedications)) {
            return null;
        }

        try {
            return DB::transaction(function () use ($finalize) {
                $prescription = \Modules\Prescriptions\Models\Prescription::create([
                    'tenant_id' => $this->appointment->tenant_id,
                    'patient_id' => $this->appointment->patient_id,
                    'prescriber_id' => auth()->id(),
                    'appointment_id' => $this->appointment->id,
                    'branch_id' => $this->appointment->branch_id,
                    'diagnosis' => $this->prescriptionDiagnosis,
                    'notes' => $this->prescriptionNotes,
                    'status' => $finalize ? \Modules\Prescriptions\Models\Prescription::STATUS_FINALIZED : \Modules\Prescriptions\Models\Prescription::STATUS_DRAFT,
                    'issued_at' => $finalize ? now() : null,
                    'finalized_at' => $finalize ? now() : null,
                    'finalized_by' => $finalize ? auth()->id() : null,
                ]);

                foreach ($this->prescriptionMedications as $index => $med) {
                    if (empty($med['medication_name'])) {
                        continue;
                    }

                    \Modules\Prescriptions\Models\PrescriptionItem::create([
                        'tenant_id' => $this->appointment->tenant_id,
                        'prescription_id' => $prescription->id,
                        'medication_name' => $med['medication_name'],
                        'generic_name' => $med['generic_name'] ?: null,
                        'dosage' => $med['dosage'] ?: null,
                        'dosage_unit' => $med['dosage_unit'] ?: null,
                        'form' => $med['form'] ?: null,
                        'frequency' => $med['frequency'] ?: null,
                        'duration' => ! empty($med['duration']) ? (int) $med['duration'] : null,
                        'duration_unit' => $med['duration_unit'] ?: null,
                        'quantity' => ! empty($med['quantity']) ? (int) $med['quantity'] : null,
                        'route' => $med['route'] ?: null,
                        'instructions' => $med['instructions'] ?: null,
                        'special_instructions' => $med['special_instructions'] ?: null,
                        'sort_order' => $index,
                        'refills_allowed' => ! empty($med['refills_allowed']) ? (int) $med['refills_allowed'] : 0,
                    ]);
                }

                // Clear the form
                $this->prescriptionMedications = [];
                $this->prescriptionDiagnosis = null;
                $this->prescriptionNotes = null;

                return $prescription;
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('prescriptions::prescription.messages.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    /**
     * Get prescription frequency options.
     */
    public function getPrescriptionFrequencies(): array
    {
        return \Modules\Prescriptions\Models\PrescriptionItem::FREQUENCIES;
    }

    /**
     * Get prescription route options.
     */
    public function getPrescriptionRoutes(): array
    {
        return \Modules\Prescriptions\Models\PrescriptionItem::ROUTES;
    }

    /**
     * Get prescription form options.
     */
    public function getPrescriptionForms(): array
    {
        return \Modules\Prescriptions\Models\PrescriptionItem::FORMS;
    }

    /**
     * Get prescription instruction options.
     */
    public function getPrescriptionInstructions(): array
    {
        return \Modules\Prescriptions\Models\PrescriptionItem::INSTRUCTIONS;
    }

    /**
     * Get duration unit options.
     */
    public function getPrescriptionDurationUnits(): array
    {
        return \Modules\Prescriptions\Models\PrescriptionItem::DURATION_UNITS;
    }

    /**
     * Get dosage unit options.
     */
    public function getPrescriptionDosageUnits(): array
    {
        return \Modules\Prescriptions\Models\PrescriptionItem::DOSAGE_UNITS;
    }

    /**
     * Get available medicines from catalog for selection.
     */
    public function getAvailableMedicines(): \Illuminate\Support\Collection
    {
        return \Modules\Prescriptions\Models\MedicineCatalog::query()
            ->withSystemMedicines()
            ->active()
            ->orderBy('brand_name')
            ->get();
    }

    /**
     * Add medication from catalog.
     */
    public function addMedicineFromCatalog(string $medicineId): void
    {
        $medicine = \Modules\Prescriptions\Models\MedicineCatalog::find($medicineId);

        if (! $medicine) {
            return;
        }

        $this->prescriptionMedications[] = $medicine->toPrescriptionItemData();
    }

    // ============================================
    // TREATMENT PLAN ADDITION METHODS
    // ============================================

    /**
     * Get available products for treatment plan (non-consumables).
     */
    public function getAvailableProductsForPlan(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->where('is_consumable', false)
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->getTranslation('name', app()->getLocale())])
            ->toArray();
    }

    /**
     * Get available packages for treatment plan.
     */
    public function getAvailablePackages(): array
    {
        return \Modules\Packages\Models\Package::query()
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->getTranslation('name', app()->getLocale())])
            ->toArray();
    }

    /**
     * Get patient's active package subscriptions with their services.
     */
    public function getPatientActivePackages(): \Illuminate\Support\Collection
    {
        if (! $this->patient) {
            return collect();
        }

        return \Modules\Packages\Models\PackageSubscription::query()
            ->where('patient_id', $this->patient->id)
            ->active()
            ->with(['package.items.service'])
            ->get();
    }

    /**
     * Check if a service is covered by any active package subscription.
     * Returns the package subscription if found, null otherwise.
     */
    public function getPackageForService(string $serviceId): ?\Modules\Packages\Models\PackageSubscription
    {
        $subscriptions = $this->getPatientActivePackages();

        foreach ($subscriptions as $subscription) {
            if ($subscription->package && $subscription->hasRemainingSessionsForService($serviceId)) {
                return $subscription;
            }
        }

        return null;
    }

    /**
     * Set item price based on selection.
     */
    public function setItemPrice(Forms\Set $set, $itemId, string $type): void
    {
        if (! $itemId) {
            return;
        }

        $price = 0;

        switch ($type) {
            case 'service':
                $service = Service::find($itemId);
                $price = $service ? ($service->base_price_minor / 100) : 0;
                break;
            case 'product':
                $product = Product::find($itemId);
                $price = $product ? ($product->sell_price_minor / 100) : 0;
                break;
            case 'package':
                $package = \Modules\Packages\Models\Package::find($itemId);
                $price = $package ? ($package->price_minor / 100) : 0;
                break;
        }

        $set('unit_price', $price);
    }

    /**
     * Add items to treatment plan (existing or new).
     */
    public function addItemsToTreatmentPlan(array $data): void
    {
        if (! $this->patient) {
            Notification::make()
                ->title(__('booking::session.messages.error'))
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($data) {
                $plan = null;

                if ($data['plan_mode'] === 'new') {
                    // Create new treatment plan
                    $plan = TreatmentPlan::create([
                        'patient_id' => $this->patient->id,
                        'branch_id' => $this->appointment->branch_id,
                        'created_by_user_id' => auth()->id(),
                        'name' => ['en' => $data['new_plan_name'], 'ar' => $data['new_plan_name']],
                        'status' => TreatmentPlan::STATUS_ACTIVE,
                        'source' => TreatmentPlan::SOURCE_CONSULTATION,
                        'start_date' => today(),
                    ]);
                } else {
                    // Get existing plan
                    $plan = TreatmentPlan::find($data['treatment_plan_id']);
                    if (! $plan) {
                        throw new \Exception('Treatment plan not found');
                    }
                }

                // Get max sort order
                $maxSortOrder = $plan->items()->max('sort_order') ?? 0;

                // Add items to plan
                foreach ($data['items'] as $index => $item) {
                    $itemType = $item['item_type'] ?? 'service';
                    $originalPrice = (float) ($item['unit_price'] ?? 0);
                    $discountType = $item['discount_type'] ?? 'none';
                    $discountValue = (float) ($item['discount_value'] ?? 0);

                    // Calculate discount amount in minor units
                    $originalPriceMinor = (int) round($originalPrice * 100);
                    $discountMinor = 0;

                    if ($discountType === 'percent' && $discountValue > 0) {
                        $discountMinor = (int) round($originalPriceMinor * $discountValue / 100);
                    } elseif ($discountType === 'fixed' && $discountValue > 0) {
                        $discountMinor = (int) round($discountValue * 100);
                    }

                    $itemData = [
                        'tenant_id' => $plan->tenant_id,
                        'treatment_plan_id' => $plan->id,
                        'item_type' => $itemType,
                        'unit_price_minor' => $originalPriceMinor, // Original price
                        'discount_minor' => $discountMinor, // Discount amount to show in invoice
                        'sort_order' => $maxSortOrder + $index + 1,
                    ];

                    switch ($itemType) {
                        case TreatmentPlanItem::TYPE_SERVICE:
                            $service = Service::find($item['service_id']);
                            $itemData['service_id'] = $item['service_id'];
                            $itemData['recommended_sessions'] = (int) ($item['sessions'] ?? 1);
                            $itemData['session_interval_days'] = (int) ($item['interval_days'] ?? 7);
                            $itemData['preferred_practitioner_id'] = $item['preferred_practitioner_id'] ?? null;
                            $itemData['itemable_type'] = Service::class;
                            $itemData['itemable_id'] = $item['service_id'];
                            break;

                        case TreatmentPlanItem::TYPE_PRODUCT:
                            $product = Product::find($item['product_id']);
                            $itemData['quantity'] = (int) ($item['quantity'] ?? 1);
                            $itemData['itemable_type'] = Product::class;
                            $itemData['itemable_id'] = $item['product_id'];
                            break;

                        case TreatmentPlanItem::TYPE_PACKAGE:
                            $package = \Modules\Packages\Models\Package::find($item['package_id']);
                            $itemData['quantity'] = (int) ($item['quantity'] ?? 1);
                            $itemData['itemable_type'] = \Modules\Packages\Models\Package::class;
                            $itemData['itemable_id'] = $item['package_id'];
                            break;
                    }

                    TreatmentPlanItem::create($itemData);
                }

                // Recalculate plan financials
                $plan->recalculateFinancials();
            });

            Notification::make()
                ->title(__('booking::session.messages.items_added_to_plan'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::session.messages.add_to_plan_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ============================================
    // INVOICE SECTION METHODS
    // ============================================

    /**
     * Get all invoice line items for display.
     */
    public function getInvoiceItems(): array
    {
        $items = [];

        // Service line item
        if ($this->appointment?->service) {
            $unitPrice = $this->servicePriceMinor ?? $this->appointment->price_minor ?? 0;
            $quantity = $this->appointment->quantity ?? 1;
            $subtotalMinor = $unitPrice * $quantity;

            $discountType = $this->serviceDiscountType ?? 'none';
            $discountValue = $this->serviceDiscountValue ?? 0;

            // Calculate discount - for fixed, discountValue is in major units
            $discountMinor = $this->calculateLineDiscountForDisplay($subtotalMinor, $discountType, $discountValue);
            $totalMinor = max(0, $subtotalMinor - $discountMinor);

            $items[] = [
                'type' => 'service',
                'id' => $this->appointment->service_id,
                'name' => $this->appointment->service->translated_name,
                'description' => __('booking::session.invoice.service_session'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice / 100,
                'unit_price_minor' => $unitPrice,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount' => $discountMinor / 100,
                'discount_minor' => $discountMinor,
                'total' => $totalMinor / 100,
                'total_minor' => $totalMinor,
                'editable' => true,
            ];
        }

        // Other active services from the treatment plan (in progress sessions)
        if ($this->appointment?->treatmentPlanAppointment?->item?->treatmentPlan) {
            $treatmentPlan = $this->appointment->treatmentPlanAppointment->item->treatmentPlan;
            $currentAppointmentId = $this->appointment->id;

            // Get item IDs from this treatment plan
            $planItemIds = $treatmentPlan->items()->pluck('id')->toArray();

            // Get other active service appointments from the same treatment plan
            $activeAppointments = \Modules\Booking\Models\Appointment::query()
                ->whereHas('treatmentPlanAppointment', function ($q) use ($planItemIds) {
                    $q->whereIn('treatment_plan_item_id', $planItemIds);
                })
                ->where('id', '!=', $currentAppointmentId)
                ->whereIn('status', [
                    \Modules\Booking\Models\Appointment::STATUS_IN_PROGRESS,
                    \Modules\Booking\Models\Appointment::STATUS_CHECKED_IN,
                    \Modules\Booking\Models\Appointment::STATUS_CONFIRMED,
                ])
                ->with(['service', 'treatmentPlanAppointment.item'])
                ->get();

            foreach ($activeAppointments as $activeAppt) {
                if (! $activeAppt->service) {
                    continue;
                }

                $planItem = $activeAppt->treatmentPlanAppointment?->item;
                $unitPrice = $activeAppt->price_minor ?? $planItem?->unit_price_minor ?? $activeAppt->service->base_price_minor ?? 0;
                $quantity = $activeAppt->quantity ?? 1;
                $subtotalMinor = $unitPrice * $quantity;

                $discountType = $activeAppt->discount_type ?? 'none';
                $discountValue = $activeAppt->discount_minor ?? 0;
                $discountMinor = $this->calculateLineDiscountForDisplay($subtotalMinor, $discountType, $discountValue);
                $totalMinor = max(0, $subtotalMinor - $discountMinor);

                $items[] = [
                    'type' => 'active_service',
                    'id' => $activeAppt->id,
                    'service_id' => $activeAppt->service_id,
                    'name' => $activeAppt->service->translated_name,
                    'description' => __('booking::session.invoice.active_session'),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice / 100,
                    'unit_price_minor' => $unitPrice,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount' => $discountMinor / 100,
                    'discount_minor' => $discountMinor,
                    'total' => $totalMinor / 100,
                    'total_minor' => $totalMinor,
                    'editable' => false, // Can't edit from here, need to go to that session
                    'appointment_id' => $activeAppt->id,
                ];
            }
        }

        // Sold products (not applied - those are consumables/cost)
        foreach ($this->sessionProducts as $index => $product) {
            if (($product['usage_type'] ?? 'applied') === 'sold') {
                $quantity = $product['quantity'] ?? 1;
                $unitPriceMinor = (int) (($product['unit_price'] ?? 0) * 100);
                $subtotalMinor = $unitPriceMinor * $quantity;

                $discountType = $product['discount_type'] ?? 'none';
                $discountValue = $product['discount_value'] ?? 0;

                // Calculate discount
                $discountMinor = $this->calculateLineDiscountForDisplay($subtotalMinor, $discountType, $discountValue);
                $totalMinor = max(0, $subtotalMinor - $discountMinor);

                $items[] = [
                    'type' => 'product',
                    'id' => $product['id'],
                    'product_id' => $product['product_id'],
                    'name' => $product['product_name'],
                    'description' => __('booking::session.invoice.product_sold'),
                    'quantity' => $quantity,
                    'unit_price' => $unitPriceMinor / 100,
                    'unit_price_minor' => $unitPriceMinor,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount' => $discountMinor / 100,
                    'discount_minor' => $discountMinor,
                    'total' => $totalMinor / 100,
                    'total_minor' => $totalMinor,
                    'editable' => true,
                ];
            }
        }

        // Treatment plan products (if appointment is part of a treatment plan)
        if ($this->appointment?->treatmentPlanAppointment?->item?->treatmentPlan) {
            $treatmentPlan = $this->appointment->treatmentPlanAppointment->item->treatmentPlan;

            // Get product items from the treatment plan
            $planProducts = $treatmentPlan->items()
                ->where('item_type', 'product')
                ->with('itemable')
                ->get();

            foreach ($planProducts as $planItem) {
                $product = $planItem->itemable;
                if (! $product) {
                    continue;
                }

                $quantity = $planItem->quantity ?? 1;
                $unitPriceMinor = $planItem->unit_price_minor ?? 0;
                $subtotalMinor = $unitPriceMinor * $quantity;
                $discountMinor = $planItem->discount_minor ?? 0;
                $totalMinor = $planItem->total_minor ?? max(0, $subtotalMinor - $discountMinor);

                $items[] = [
                    'type' => 'plan_product',
                    'id' => $planItem->id,
                    'product_id' => $product->id,
                    'name' => $product->translated_name ?? $product->name,
                    'description' => __('booking::session.invoice.plan_product'),
                    'quantity' => $quantity,
                    'unit_price' => $unitPriceMinor / 100,
                    'unit_price_minor' => $unitPriceMinor,
                    'discount_type' => 'fixed',
                    'discount_value' => $discountMinor / 100,
                    'discount' => $discountMinor / 100,
                    'discount_minor' => $discountMinor,
                    'total' => $totalMinor / 100,
                    'total_minor' => $totalMinor,
                    'editable' => true,
                    'is_delivered' => $planItem->is_delivered,
                    'invoiced_quantity' => $planItem->invoiced_quantity ?? 0,
                ];
            }
        }

        return $items;
    }

    /**
     * Get visit summary for display - shows other appointments in the same visit.
     */
    public function getVisitSummary(): ?array
    {
        if (! $this->visit) {
            return null;
        }

        // Refresh visit with appointments
        $this->visit->load(['appointments.service', 'appointments.practitioner', 'products.product']);

        $otherAppointments = $this->visit->appointments
            ->filter(fn ($appt) => $appt->id !== $this->appointment?->id)
            ->map(fn ($appt) => [
                'id' => $appt->id,
                'service' => $appt->service?->translated_name ?? '-',
                'practitioner' => $appt->practitioner?->full_name ?? '-',
                'status' => $appt->status,
                'status_label' => __('booking::appointments.statuses.'.$appt->status),
                'price' => $appt->net_price,
            ]);

        $visitProducts = $this->visit->products
            ->filter(fn ($prod) => $prod->appointment_id !== $this->appointment?->id)
            ->map(fn ($prod) => [
                'id' => $prod->id,
                'name' => $prod->product?->translated_name ?? '-',
                'quantity' => $prod->quantity,
                'total' => $prod->total_price,
                'usage_type' => $prod->usage_type,
            ]);

        return [
            'code' => $this->visit->code,
            'check_in_at' => $this->visit->check_in_at,
            'status' => $this->visit->status,
            'other_appointments' => $otherAppointments,
            'other_products' => $visitProducts->where('usage_type', 'sold'),
            'total_appointments' => $this->visit->appointments->count(),
            'total_products' => $this->visit->products->where('usage_type', 'sold')->count(),
        ];
    }

    /**
     * Calculate discount for display (handles major/minor unit conversion).
     */
    protected function calculateLineDiscountForDisplay(int $priceMinor, string $discountType, $discountValue): int
    {
        if ($discountType === 'none' || ! $discountValue || $discountValue <= 0) {
            return 0;
        }

        if ($discountType === 'percent') {
            return (int) round($priceMinor * $discountValue / 100);
        }

        // Fixed discount - value is in major units, convert to minor
        $discountMinor = (int) ($discountValue * 100);

        return min($discountMinor, $priceMinor);
    }

    /**
     * Update invoice item quantity.
     */
    public function updateInvoiceItemQuantity(string $type, string $id, $quantity): void
    {
        $quantity = max(0.01, (float) $quantity);

        if ($type === 'service') {
            $oldQuantity = (float) ($this->appointment->quantity ?? 1);

            // Update appointment quantity
            $this->appointment->update(['quantity' => $quantity]);
            $this->appointment->refresh();

            // Auto-update consumables proportionally
            $this->updateConsumablesForQuantityChange($oldQuantity, $quantity);

            // Reload consumables to refresh UI
            $this->loadConsumablesAndProducts();

            Notification::make()
                ->title('Quantity updated')
                ->body('Consumables adjusted automatically')
                ->success()
                ->duration(2000)
                ->send();
        } elseif ($type === 'product') {
            // Update in database first
            $sessionProduct = SessionProduct::find($id);
            if ($sessionProduct) {
                $sessionProduct->quantity = $quantity;
                $sessionProduct->save();

                // Reload products to get updated totals
                $this->loadConsumablesAndProducts();

                Notification::make()
                    ->title('Quantity updated')
                    ->success()
                    ->duration(2000)
                    ->send();
            }
        } elseif ($type === 'plan_product') {
            // Update treatment plan item - use withoutGlobalScope for tenant schema isolation
            $planItem = \Modules\TreatmentPlans\Models\TreatmentPlanItem::withoutGlobalScope('tenant')->find($id);
            if ($planItem) {
                $planItem->quantity = (int) $quantity;
                $planItem->save();

                Notification::make()
                    ->title('Quantity updated')
                    ->success()
                    ->duration(2000)
                    ->send();
            }
        }
    }

    /**
     * Update consumables proportionally when service quantity changes.
     * Uses base_quantity (per 1 service unit) to calculate new quantity.
     */
    protected function updateConsumablesForQuantityChange(float $oldQuantity, float $newQuantity): void
    {
        if ($newQuantity <= 0) {
            return;
        }

        foreach ($this->sessionConsumables as $index => $consumable) {
            // Use base_quantity to calculate: new_qty = base_qty * service_qty
            $baseQty = (float) ($consumable['base_quantity'] ?? $consumable['quantity']);
            $newConsumableQty = round($baseQty * $newQuantity, 2);
            $newTotalCost = $consumable['unit_cost'] * $newConsumableQty;

            // Update in database (unit_cost is in major units, convert to minor for storage)
            SessionConsumable::where('id', $consumable['id'])->update([
                'quantity' => $newConsumableQty,
                'total_cost_minor' => (int) ($newTotalCost * 100),
            ]);

            // Update local array (keep in major units for display)
            $this->sessionConsumables[$index]['quantity'] = $newConsumableQty;
            $this->sessionConsumables[$index]['total_cost'] = $newTotalCost;
        }
    }

    /**
     * Update invoice item price.
     */
    public function updateInvoiceItemPrice(string $type, string $id, $priceMinor): void
    {
        $priceMinor = max(0, (int) $priceMinor);

        if ($type === 'service') {
            $this->servicePriceMinor = $priceMinor;
            $this->appointment?->update(['price_minor' => $priceMinor]);

            Notification::make()
                ->title('Price updated')
                ->success()
                ->duration(2000)
                ->send();
        } elseif ($type === 'product') {
            // Update in database first
            $sessionProduct = SessionProduct::find($id);
            if ($sessionProduct) {
                $sessionProduct->unit_price_minor = $priceMinor;
                $sessionProduct->save();

                // Reload products to get updated totals
                $this->loadConsumablesAndProducts();

                Notification::make()
                    ->title('Price updated')
                    ->success()
                    ->duration(2000)
                    ->send();
            }
        } elseif ($type === 'plan_product') {
            // Update treatment plan item - use withoutGlobalScope for tenant schema isolation
            $planItem = \Modules\TreatmentPlans\Models\TreatmentPlanItem::withoutGlobalScope('tenant')->find($id);
            if ($planItem) {
                $planItem->unit_price_minor = $priceMinor;
                $planItem->save();

                Notification::make()
                    ->title('Price updated')
                    ->success()
                    ->duration(2000)
                    ->send();
            }
        }
    }

    /**
     * Update invoice item discount type.
     */
    public function updateInvoiceItemDiscountType(string $type, string $id, string $discountType): void
    {
        if ($type === 'service') {
            $this->serviceDiscountType = $discountType;
            if ($discountType === 'none') {
                $this->serviceDiscountValue = 0;
            }
            // Save to appointment
            $this->saveServiceDiscountToAppointment();
        } elseif ($type === 'product') {
            // Update in database first
            $sessionProduct = SessionProduct::find($id);
            if ($sessionProduct) {
                $sessionProduct->discount_type = $discountType;
                if ($discountType === 'none') {
                    $sessionProduct->discount_value = 0;
                }
                $sessionProduct->save();

                // Reload products to get updated totals
                $this->loadConsumablesAndProducts();
            }
        } elseif ($type === 'plan_product') {
            // Update treatment plan item - discount is stored as minor units - use withoutGlobalScope for tenant schema isolation
            $planItem = \Modules\TreatmentPlans\Models\TreatmentPlanItem::withoutGlobalScope('tenant')->find($id);
            if ($planItem) {
                if ($discountType === 'none') {
                    $planItem->discount_minor = 0;
                }
                $planItem->save();

                Notification::make()
                    ->title('Discount updated')
                    ->success()
                    ->duration(2000)
                    ->send();
            }
        }
    }

    /**
     * Update invoice item discount value.
     */
    public function updateInvoiceItemDiscountValue(string $type, string $id, $discountValue): void
    {
        $discountValue = max(0, (float) $discountValue);

        if ($type === 'service') {
            $this->serviceDiscountValue = (int) $discountValue;
            // Save to appointment
            $this->saveServiceDiscountToAppointment();
        } elseif ($type === 'product') {
            // Update in database first
            $sessionProduct = SessionProduct::find($id);
            if ($sessionProduct) {
                $sessionProduct->discount_value = $discountValue;
                $sessionProduct->save();

                // Reload products to get updated totals
                $this->loadConsumablesAndProducts();
            }
        } elseif ($type === 'plan_product') {
            // Update treatment plan item - discount is stored as minor units (fixed amount) - use withoutGlobalScope for tenant schema isolation
            $planItem = \Modules\TreatmentPlans\Models\TreatmentPlanItem::withoutGlobalScope('tenant')->find($id);
            if ($planItem) {
                // Convert to minor units if it's a fixed amount
                $planItem->discount_minor = (int) ($discountValue * 100);
                $planItem->save();

                Notification::make()
                    ->title('Discount updated')
                    ->success()
                    ->duration(2000)
                    ->send();
            }
        }
    }

    /**
     * Toggle editing mode for an invoice item.
     */
    public function toggleInvoiceItemEdit(string $type, string $id): void
    {
        $itemKey = $type === 'service' ? 'service' : "product-{$id}";

        if ($this->editingInvoiceItem === $itemKey) {
            $this->editingInvoiceItem = null;
        } else {
            $this->editingInvoiceItem = $itemKey;
        }
    }

    /**
     * Check if an invoice item is being edited.
     */
    public function isEditingInvoiceItem(string $type, string $id): bool
    {
        $itemKey = $type === 'service' ? 'service' : "product-{$id}";

        return $this->editingInvoiceItem === $itemKey;
    }

    /**
     * Save service discount to appointment.
     */
    protected function saveServiceDiscountToAppointment(): void
    {
        if (! $this->appointment) {
            return;
        }

        $discountType = $this->serviceDiscountType ?? 'none';
        $discountValue = $this->serviceDiscountValue ?? 0;

        if ($discountType === 'none' || $discountValue <= 0) {
            $this->appointment->update([
                'discount_type' => 'fixed',
                'discount_minor' => 0,
            ]);
        } else {
            // Calculate discount_minor based on type - always store as fixed amount
            $priceMinor = $this->appointment->price_minor ?? 0;

            if ($discountType === 'percent') {
                // Calculate discount amount from percentage
                $discountMinor = (int) round($priceMinor * $discountValue / 100);
            } else {
                // Convert from EGP to piastres
                $discountMinor = (int) ($discountValue * 100);
            }

            $this->appointment->update([
                'discount_type' => 'fixed',
                'discount_minor' => $discountMinor,
            ]);
        }
    }

    /**
     * Calculate discount amount for a line item.
     */
    protected function calculateLineDiscount(int $priceMinor, string $discountType, int $discountValue): int
    {
        if ($discountType === 'none' || $discountValue <= 0) {
            return 0;
        }

        if ($discountType === 'percent') {
            return (int) round($priceMinor * $discountValue / 100);
        }

        // Fixed discount
        return min($discountValue, $priceMinor);
    }

    /**
     * Get invoice subtotal (before overall discount).
     */
    public function getInvoiceSubtotal(): float
    {
        $items = $this->getInvoiceItems();

        return collect($items)->sum('total');
    }

    /**
     * Get invoice subtotal in minor units.
     */
    public function getInvoiceSubtotalMinor(): int
    {
        $items = $this->getInvoiceItems();

        return (int) collect($items)->sum('total_minor');
    }

    /**
     * Get overall discount amount.
     */
    public function getOverallDiscountAmount(): float
    {
        return $this->getOverallDiscountAmountMinor() / 100;
    }

    /**
     * Get overall discount amount in minor units.
     */
    public function getOverallDiscountAmountMinor(): int
    {
        if (! $this->overallDiscountType || $this->overallDiscountType === 'none' || ! $this->overallDiscountValue) {
            return 0;
        }

        $subtotal = $this->getInvoiceSubtotalMinor();

        // Convert discount value to what calculateLineDiscount expects
        // Percentage: use as-is (10 means 10%)
        // Fixed: convert from major units (display) to minor units
        $discountValue = $this->overallDiscountType === 'fixed'
            ? (int) ($this->overallDiscountValue * 100)
            : (int) $this->overallDiscountValue;

        return $this->calculateLineDiscount($subtotal, $this->overallDiscountType, $discountValue);
    }

    /**
     * Get invoice total (after all discounts).
     */
    public function getInvoiceTotal(): float
    {
        return $this->getInvoiceTotalMinor() / 100;
    }

    /**
     * Get invoice total in minor units.
     */
    public function getInvoiceTotalMinor(): int
    {
        return max(0, $this->getInvoiceSubtotalMinor() - $this->getOverallDiscountAmountMinor());
    }

    /**
     * Update service price.
     */
    public function updateServicePrice(int $priceMinor): void
    {
        $this->servicePriceMinor = max(0, $priceMinor);

        // Update appointment price
        if ($this->appointment) {
            $this->appointment->update(['price_minor' => $this->servicePriceMinor]);
        }
    }

    /**
     * Update service discount.
     */
    public function updateServiceDiscount(string $type, float $value): void
    {
        $this->serviceDiscountType = $type;
        $this->serviceDiscountValue = max(0, $value);

        // Update appointment discount - always calculate and store as fixed amount
        if ($this->appointment) {
            $priceMinor = $this->appointment->price_minor ?? 0;

            if ($type === 'percent' && $value > 0) {
                // Calculate discount amount from percentage
                $discountMinor = (int) round($priceMinor * $value / 100);
            } elseif ($type === 'fixed' && $value > 0) {
                // Convert from EGP to piastres
                $discountMinor = (int) ($value * 100);
            } else {
                $discountMinor = 0;
            }

            $this->appointment->update([
                'discount_type' => 'fixed',
                'discount_minor' => $discountMinor,
            ]);
        }
    }

    /**
     * Update product price.
     */
    public function updateProductPrice(string $productId, int $priceMinor): void
    {
        $sessionProduct = SessionProduct::find($productId);
        if ($sessionProduct) {
            $sessionProduct->update(['unit_price_minor' => max(0, $priceMinor)]);
            $this->loadConsumablesAndProducts();
        }
    }

    /**
     * Apply overall discount.
     * Uses the current values from wire:model properties.
     */
    public function applyOverallDiscount(): void
    {
        // Values are already set via wire:model.live
        $this->overallDiscountValue = max(0, $this->overallDiscountValue ?? 0);

        // Store discount in appointment - always calculate and store as fixed amount
        if ($this->appointment) {
            if ($this->overallDiscountType !== 'none' && $this->overallDiscountValue > 0) {
                $priceMinor = $this->appointment->price_minor ?? 0;

                if ($this->overallDiscountType === 'percent') {
                    // Calculate discount amount from percentage
                    $discountMinor = (int) round($priceMinor * $this->overallDiscountValue / 100);
                } else {
                    // For fixed discounts, user enters in major units, convert to minor
                    $discountMinor = (int) ($this->overallDiscountValue * 100);
                }

                $this->appointment->update([
                    'discount_type' => 'fixed',
                    'discount_minor' => $discountMinor,
                    'discount_reason' => $this->overallDiscountReason,
                ]);
            } else {
                $this->appointment->update([
                    'discount_type' => 'fixed',
                    'discount_minor' => 0,
                    'discount_reason' => null,
                ]);
            }
        }

        Notification::make()
            ->title(__('booking::session.invoice.discount_applied'))
            ->success()
            ->send();
    }

    /**
     * Check if session has billable items.
     */
    public function hasBillableItems(): bool
    {
        return ! empty($this->getInvoiceItems());
    }

    /**
     * Get packages pending purchase for this visit.
     */
    public function getPendingPackages(): \Illuminate\Support\Collection
    {
        if (! $this->visit) {
            return collect();
        }

        return $this->visit->pendingPackages;
    }

    /**
     * Add a package for purchase at checkout.
     */
    public function addPackageToPurchase(int $packageId): void
    {
        if (! $this->visit) {
            Notification::make()
                ->title(__('booking::session.messages.no_visit'))
                ->danger()
                ->send();

            return;
        }

        $package = \Modules\Packages\Models\Package::find($packageId);
        if (! $package) {
            return;
        }

        // Check if already pending
        if ($this->visit->hasPendingPackage($packageId)) {
            Notification::make()
                ->title(__('booking::session.messages.package_already_pending'))
                ->warning()
                ->send();

            return;
        }

        // Add to visit
        $this->visit->addPendingPackage($package);
        $this->visit->load('pendingPackages'); // Refresh the relationship

        Notification::make()
            ->title(__('booking::session.messages.package_added'))
            ->body(__('booking::session.messages.package_added_body', [
                'package' => $package->translated_name,
            ]))
            ->success()
            ->send();
    }

    /**
     * Remove a package from pending purchase.
     */
    public function removePendingPackage(int $packageId): void
    {
        if (! $this->visit) {
            return;
        }

        $this->visit->removePendingPackage(\Modules\Packages\Models\Package::find($packageId));
        $this->visit->load('pendingPackages'); // Refresh the relationship

        Notification::make()
            ->title(__('booking::session.messages.package_removed'))
            ->success()
            ->send();
    }

    /**
     * Get packages available for purchase (active packages not already owned by patient).
     */
    public function getPurchasablePackages(): \Illuminate\Support\Collection
    {
        $packages = \Modules\Packages\Models\Package::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get patient's active subscriptions
        $activeSubscriptionPackageIds = $this->getPatientActivePackages()
            ->pluck('package_id')
            ->toArray();

        // Get pending package IDs
        $pendingPackageIds = $this->getPendingPackages()
            ->pluck('id')
            ->toArray();

        // Filter out already owned or pending packages
        return $packages->filter(function ($package) use ($activeSubscriptionPackageIds, $pendingPackageIds) {
            return ! in_array($package->id, $activeSubscriptionPackageIds)
                && ! in_array($package->id, $pendingPackageIds);
        });
    }
}
