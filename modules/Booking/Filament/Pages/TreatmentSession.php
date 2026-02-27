<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\TreatmentSessionData;
use Modules\Patients\Models\Patient;
use Modules\Patients\Models\PatientNote;
use Modules\Patients\Models\PatientPhoto;
use Modules\Patients\Models\PatientMedicalHistory;
use Modules\Patients\Models\MedicalProfile;
use Modules\Patients\Models\PatientAmrSummary;
use Modules\TreatmentPlans\Models\TreatmentPlan;
use Modules\TreatmentPlans\Models\TreatmentPlanItem;
use Modules\Services\Models\Service;
use Modules\Services\Models\ParameterPreset;
use Modules\Equipment\Models\Equipment;
use Modules\Inventory\Models\Product;
use Modules\Booking\Models\SessionConsumable;
use Modules\Booking\Models\SessionProduct;

class TreatmentSession extends Page implements HasForms, HasInfolists, HasActions
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use InteractsWithActions;
    use ChecksResourcePermissions;
    use WithFileUploads;

    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'appointments';
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

    public ?Appointment $appointment = null;
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
    public ?string $newProductUsageType = 'applied';

    // Prescription data
    public array $prescriptionMedications = [];
    public ?string $prescriptionDiagnosis = null;
    public ?string $prescriptionNotes = null;

    public function mount(): void
    {
        $this->loadAppointment();

        if (!$this->appointment) {
            Notification::make()
                ->title(__('booking::session.messages.appointment_not_found'))
                ->danger()
                ->send();
            $this->redirect(DoctorDashboard::getUrl());
            return;
        }

        // Ensure appointment is in progress
        if ($this->appointment->status !== Appointment::STATUS_IN_PROGRESS) {
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
                ['service_id' => null, 'sessions' => 1, 'interval' => 7]
            ],
            'notes' => '',
        ];

        // Load or create session data
        $this->loadOrCreateSessionData();
    }

    protected function loadAppointment(): void
    {
        $this->appointment = Appointment::with([
            'patient.medicalHistory',
            'patient.medicalProfile',
            'patient.amrSummary',
            'service',
            'practitioner',
            'room',
            'branch',
            'treatmentPlanAppointment.item.treatmentPlan',
        ])->find($this->appointment_id);

        if ($this->appointment) {
            $this->patient = $this->appointment->patient;
            $this->medicalHistory = $this->patient?->medicalHistory;
            $this->medicalProfile = $this->patient?->medicalProfile;
            $this->amrSummary = $this->patient?->amrSummary;
        }
    }

    protected function loadOrCreateSessionData(): void
    {
        if (!$this->appointment) {
            return;
        }

        // Try to load existing session data
        $this->sessionData = TreatmentSessionData::where('appointment_id', $this->appointment->id)->first();

        if (!$this->sessionData) {
            // Create new session data
            $this->sessionData = TreatmentSessionData::create([
                'tenant_id' => $this->appointment->tenant_id,
                'appointment_id' => $this->appointment->id,
                'service_id' => $this->appointment->service_id,
                'equipment_id' => $this->appointment->equipment_id,
                'practitioner_id' => $this->appointment->practitioner_id,
                'session_started_at' => $this->appointment->started_at ?? now(),
            ]);
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
        if (!$this->appointment) {
            return;
        }

        $this->sessionConsumables = SessionConsumable::where('appointment_id', $this->appointment->id)
            ->with('product')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'product_id' => $c->product_id,
                'product_name' => $c->product?->getTranslation('name', app()->getLocale()) ?? '',
                'quantity' => $c->quantity,
                'unit' => $c->unit,
                'unit_cost' => $c->unit_cost,
                'total_cost' => $c->total_cost,
            ])
            ->toArray();

        $this->sessionProducts = SessionProduct::where('appointment_id', $this->appointment->id)
            ->with('product')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'product_id' => $p->product_id,
                'product_name' => $p->product?->getTranslation('name', app()->getLocale()) ?? '',
                'quantity' => $p->quantity,
                'unit' => $p->unit,
                'unit_price' => $p->unit_price,
                'total_price' => $p->total_price,
                'usage_type' => $p->usage_type,
            ])
            ->toArray();
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
            return $this->patient->full_name . ' - ' . $this->appointment?->service?->translated_name;
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
                                ->options(TreatmentPlanItem::TYPES)
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
                            Forms\Components\Select::make('product_id')
                                ->label(__('booking::session.plan_modal.product'))
                                ->options(fn () => $this->getAvailableProductsForPlan())
                                ->visible(fn (Forms\Get $get) => $get('item_type') === 'product')
                                ->required(fn (Forms\Get $get) => $get('item_type') === 'product')
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set, $state) => $this->setItemPrice($set, $state, 'product')),
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
                                ->visible(fn (Forms\Get $get) => in_array($get('item_type'), ['product', 'package'])),
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
                                    if (!$serviceId) {
                                        return [];
                                    }
                                    $service = Service::find($serviceId);
                                    if (!$service) {
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
                                    ? current_currency() . ' ' . number_format((float) $get('unit_price'), 2)
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
                                        '<span class="font-semibold text-success-600">' . current_currency() . ' ' . number_format($finalPrice, 2) . '</span>'
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

            Action::make('startAnotherSession')
                ->label(__('booking::session.actions.start_another_session'))
                ->icon('heroicon-o-play')
                ->color('info')
                ->extraAttributes(['style' => 'display: none;'])
                ->form(function () {
                    $item = $this->pendingSessionItemId ? TreatmentPlanItem::find($this->pendingSessionItemId) : null;
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
                            ->visible(fn (Forms\Get $get) => $get('action') === 'assign_doctor' && !empty($qualifiedPractitioners))
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
        if (!$this->appointment) {
            return;
        }

        // Check if checklist is complete (if service has parameters)
        if ($this->hasServiceParameters() && !$this->isChecklistComplete()) {
            Notification::make()
                ->title(__('booking::session.messages.checklist_incomplete'))
                ->body(__('booking::session.messages.complete_checklist_first'))
                ->warning()
                ->send();
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

            // Update equipment shot counts for all session equipment
            foreach ($this->sessionEquipment as $equipmentData) {
                if (!empty($equipmentData['shots_used'])) {
                    $equipment = Equipment::find($equipmentData['equipment_id']);
                    if ($equipment) {
                        $equipment->recordShots(
                            (int) $equipmentData['shots_used'],
                            $this->appointment->id,
                            $this->parameterValues
                        );
                    }
                }
            }

            $this->appointment->complete();

            // Update treatment plan progress if linked
            if ($planAppointment = $this->appointment->treatmentPlanAppointment) {
                $planAppointment->update(['status' => Appointment::STATUS_COMPLETED]);
                $planAppointment->item->incrementCompletedSessions();
                $planAppointment->item->treatmentPlan->checkAndMarkComplete();
            }

            // Mark consumables as deducted
            SessionConsumable::where('appointment_id', $this->appointment->id)
                ->where('is_deducted', false)
                ->update([
                    'is_deducted' => true,
                    'deducted_at' => now(),
                    'deducted_by' => auth()->id(),
                ]);

            // Mark products as deducted
            SessionProduct::where('appointment_id', $this->appointment->id)
                ->where('is_deducted', false)
                ->update([
                    'is_deducted' => true,
                    'deducted_at' => now(),
                ]);
        });

        Notification::make()
            ->title(__('booking::session.messages.session_completed'))
            ->success()
            ->send();

        $this->redirect(DoctorDashboard::getUrl());
    }

    public function applyDiscount(array $data): void
    {
        if (!$this->appointment) {
            return;
        }

        $discountType = $data['discount_type'] ?? 'fixed';
        $discountValue = (int) ($data['discount_value'] ?? 0);
        $discountReason = $data['discount_reason'] ?? null;

        $this->appointment->update([
            'discount_type' => $discountType,
            'discount_minor' => $discountValue,
            'discount_reason' => $discountReason,
        ]);

        $this->appointment->refresh();

        Notification::make()
            ->title(__('booking::session.messages.discount_applied'))
            ->body(__('booking::session.messages.discount_applied_body', [
                'amount' => number_format($this->appointment->getDiscountAmountMinor() / 100, 2),
                'final' => number_format($this->appointment->net_price / 100, 2),
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

        if (!$practitionerId) {
            return false;
        }

        $service = Service::find($serviceId);
        if (!$service) {
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
        $item = TreatmentPlanItem::find($itemId);

        if (!$item || !$item->canBook()) {
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
        if (!$service) {
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
        $item = TreatmentPlanItem::find($this->pendingSessionItemId);

        if (!$item || !$item->canBook()) {
            Notification::make()
                ->title(__('booking::session.messages.error'))
                ->body(__('booking::session.messages.cannot_start_session'))
                ->danger()
                ->send();
            return;
        }

        $action = $data['action'] ?? 'keep_open';
        $practitionerId = $data['practitioner_id'] ?? $this->appointment->practitioner_id;

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
            'price_minor' => $item->unit_price_minor,
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

        // Confirm and check in if starting now
        if ($redirectToNewSession) {
            $newAppointment->confirm();
            $newAppointment->checkIn();

            Notification::make()
                ->title(__('booking::session.messages.session_started'))
                ->body($item->service?->translated_name)
                ->success()
                ->send();

            $this->redirect(static::getUrl(['appointment_id' => $newAppointment->id]));
        } else {
            // Just confirm for another doctor
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
        if (!$this->appointment || !$this->appointment->hasDiscount()) {
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
                            ->suffix(' ' . __('booking::session.patient.years')),
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
        if (!$this->medicalHistory) {
            return false;
        }

        return !empty($this->medicalHistory->allergies) ||
               !empty($this->medicalHistory->contraindications) ||
               !empty($this->medicalHistory->current_medications);
    }

    /**
     * Check if patient has AMR alerts (MDRO or resistances)
     */
    public function hasAmrAlerts(): bool
    {
        if (!$this->amrSummary) {
            return false;
        }

        return $this->amrSummary->has_any_data;
    }

    /**
     * Get AMR summary data for display
     */
    public function getAmrSummaryData(): array
    {
        if (!$this->amrSummary) {
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
        if (!$this->amrSummary || empty($this->amrSummary->mdro_flags)) {
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
        if (!$this->amrSummary || empty($this->amrSummary->known_resistances)) {
            return [];
        }

        return $this->amrSummary->getFormattedResistances();
    }

    public function getPatientNotes(): Collection
    {
        if (!$this->patient) {
            return collect();
        }

        return PatientNote::where('patient_id', $this->patient->id)
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function getPatientPhotos(): Collection
    {
        if (!$this->patient) {
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
        if (!$this->patient) {
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
        if (!$this->appointment?->treatmentPlanAppointment) {
            return null;
        }

        return $this->appointment->treatmentPlanAppointment->item->treatmentPlan;
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
            'subject' => __('booking::session.notes.session_note') . ' - ' . $this->appointment->service?->translated_name,
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
        if (!$this->photoUpload) {
            Notification::make()
                ->title(__('booking::session.messages.photo_required'))
                ->warning()
                ->send();
            return;
        }

        $photo = PatientPhoto::create([
            'patient_id' => $this->patient->id,
            'appointment_id' => $this->appointment->id,
            'type' => $this->photoType,
            'body_area' => $this->photoBodyArea,
            'description' => $this->photoDescription ?? $this->getPhotoDescription(),
            'taken_at' => now(),
            'taken_by' => auth()->id(),
        ]);

        $photo->addMedia($this->photoUpload->getRealPath())
            ->usingName($this->getPhotoName())
            ->toMediaCollection('photos');

        $this->photoUpload = null;
        $this->photoDescription = null;

        Notification::make()
            ->title(__('booking::session.messages.photo_uploaded'))
            ->success()
            ->send();
    }

    protected function getPhotoDescription(): string
    {
        $sessionNum = '';
        if ($planAppointment = $this->appointment?->treatmentPlanAppointment) {
            $sessionNum = ' - Session ' . $planAppointment->session_number;
        }

        return $this->appointment?->service?->translated_name . $sessionNum . ' - ' . now()->format('M d, Y');
    }

    protected function getPhotoName(): string
    {
        return 'patient_' . ($this->patient?->id ?? 'unknown') . '_' . now()->format('Y-m-d_His');
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
        if (!$this->patient) {
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
        if (!$this->appointment?->service) {
            return [];
        }

        return $this->appointment->service->getParameterDefinitions();
    }

    public function hasServiceParameters(): bool
    {
        return $this->appointment?->service?->hasParameters() ?? false;
    }

    public function updateParameterValue(string $key, $value): void
    {
        $this->parameterValues[$key] = $value;
        $this->saveParameterValues();
    }

    public function saveParameterValues(): void
    {
        if (!$this->sessionData) {
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
        if (!$this->appointment) {
            return;
        }

        // Load from session data if available
        $savedEquipment = $this->sessionData->session_equipment ?? [];
        $savedParameterValues = $this->sessionData->equipment_parameter_values ?? [];

        if (!empty($savedEquipment)) {
            $this->sessionEquipment = $savedEquipment;
            $this->equipmentParameterValues = $savedParameterValues;
            return;
        }

        // Auto-load equipment from service requirements
        $serviceEquipment = [];
        if ($this->appointment->service) {
            $requiredEquipment = $this->appointment->service->requiredEquipment()
                ->where('branch_id', $this->appointment->branch_id)
                ->where('status', Equipment::STATUS_ACTIVE)
                ->with('trackingParameters')
                ->get();

            foreach ($requiredEquipment as $equipment) {
                $serviceEquipment[] = [
                    'id' => $equipment->id,
                    'equipment_id' => $equipment->id,
                    'name' => $equipment->name,
                    'code' => $equipment->code,
                    'is_preset' => true, // From service requirements
                    'has_tracking' => $equipment->hasTracking(),
                    'shots_used' => null,
                    'energy_delivered' => null,
                ];

                // Initialize parameter values with defaults
                if ($equipment->hasTracking()) {
                    $this->equipmentParameterValues[$equipment->id] = $equipment->getDefaultParameterValues();
                }
            }
        }

        // Also add equipment from appointment if set
        if ($this->appointment->equipment_id && !collect($serviceEquipment)->pluck('equipment_id')->contains($this->appointment->equipment_id)) {
            $equipment = Equipment::with('trackingParameters')->find($this->appointment->equipment_id);
            if ($equipment) {
                $serviceEquipment[] = [
                    'id' => $equipment->id,
                    'equipment_id' => $equipment->id,
                    'name' => $equipment->name,
                    'code' => $equipment->code,
                    'is_preset' => true,
                    'has_tracking' => $equipment->hasTracking(),
                    'shots_used' => null,
                    'energy_delivered' => null,
                ];

                // Initialize parameter values with defaults
                if ($equipment->hasTracking()) {
                    $this->equipmentParameterValues[$equipment->id] = $equipment->getDefaultParameterValues();
                }
            }
        }

        $this->sessionEquipment = $serviceEquipment;

        // Save to session data
        if ($this->sessionData && !empty($serviceEquipment)) {
            $this->sessionData->update([
                'session_equipment' => $serviceEquipment,
                'equipment_parameter_values' => $this->equipmentParameterValues,
            ]);
        }
    }

    public function getAvailableEquipment(): Collection
    {
        if (!$this->appointment) {
            return collect();
        }

        // Get equipment IDs already in session
        $usedEquipmentIds = collect($this->sessionEquipment)->pluck('equipment_id')->toArray();

        return Equipment::query()
            ->where('branch_id', $this->appointment->branch_id)
            ->where('status', Equipment::STATUS_ACTIVE)
            ->whereNotIn('id', $usedEquipmentIds)
            ->get();
    }

    public function addEquipment(): void
    {
        if (!$this->newEquipmentId) {
            return;
        }

        $equipment = Equipment::with('trackingParameters')->find($this->newEquipmentId);
        if (!$equipment) {
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

        $this->sessionEquipment[] = [
            'id' => $equipment->id,
            'equipment_id' => $equipment->id,
            'name' => $equipment->name,
            'code' => $equipment->code,
            'is_preset' => false,
            'has_tracking' => $equipment->hasTracking(),
            'shots_used' => null,
            'energy_delivered' => null,
        ];

        // Initialize parameter values with defaults if equipment has tracking
        if ($equipment->hasTracking()) {
            $this->equipmentParameterValues[$equipment->id] = $equipment->getDefaultParameterValues();
        }

        // Save to session data
        if ($this->sessionData) {
            $this->sessionData->update([
                'session_equipment' => $this->sessionEquipment,
                'equipment_parameter_values' => $this->equipmentParameterValues,
            ]);
        }

        $this->newEquipmentId = null;

        Notification::make()
            ->title(__('booking::session.equipment.added'))
            ->success()
            ->send();
    }

    public function removeEquipment(string $equipmentId): void
    {
        $this->sessionEquipment = array_values(array_filter(
            $this->sessionEquipment,
            fn ($e) => $e['equipment_id'] !== $equipmentId
        ));

        // Remove parameter values for this equipment
        unset($this->equipmentParameterValues[$equipmentId]);

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
            if ($equipment['equipment_id'] === $equipmentId) {
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

        if (!$equipment || !$equipment->hasTracking()) {
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

        if (!$equipment || !$equipment->hasTracking()) {
            return [];
        }

        return $equipment->getTrackingParametersByCategory();
    }

    /**
     * Update an equipment parameter value.
     */
    public function updateEquipmentParameterValue(string $equipmentId, string $key, $value): void
    {
        if (!isset($this->equipmentParameterValues[$equipmentId])) {
            $this->equipmentParameterValues[$equipmentId] = [];
        }

        $this->equipmentParameterValues[$equipmentId][$key] = $value;

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
        return $this->equipmentParameterValues[$equipmentId][$key] ?? null;
    }

    /**
     * Check if any equipment has tracking parameters.
     */
    public function hasEquipmentWithTracking(): bool
    {
        foreach ($this->sessionEquipment as $equipment) {
            if (!empty($equipment['has_tracking'])) {
                return true;
            }
        }
        return false;
    }

    // ============================================
    // PRESET METHODS
    // ============================================

    public function getAvailablePresets(): Collection
    {
        if (!$this->appointment?->service_id) {
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
        if (!$preset) {
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
            if (!$value) {
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
        if (!$this->sessionData) {
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
        return Product::query()
            ->where('is_active', true)
            ->where('is_consumable', true)
            ->get();
    }

    public function addConsumable(): void
    {
        if (!$this->newConsumableId || !$this->appointment) {
            return;
        }

        $product = Product::find($this->newConsumableId);
        if (!$product) {
            return;
        }

        $consumable = SessionConsumable::create([
            'tenant_id' => $this->appointment->tenant_id,
            'appointment_id' => $this->appointment->id,
            'product_id' => $product->id,
            'branch_id' => $this->appointment->branch_id,
            'quantity' => $this->newConsumableQty ?? 1,
            'unit' => $product->unit,
            'unit_cost_minor' => $product->cost_price_minor,
            'created_by' => auth()->id(),
        ]);

        $this->sessionConsumables[] = [
            'id' => $consumable->id,
            'product_id' => $consumable->product_id,
            'product_name' => $product->getTranslation('name', app()->getLocale()),
            'quantity' => $consumable->quantity,
            'unit' => $consumable->unit,
            'unit_cost' => $consumable->unit_cost,
            'total_cost' => $consumable->total_cost,
        ];

        $this->newConsumableId = null;
        $this->newConsumableQty = 1;

        Notification::make()
            ->title(__('booking::session.messages.consumable_added'))
            ->success()
            ->send();
    }

    public function removeConsumable(string $consumableId): void
    {
        SessionConsumable::where('id', $consumableId)->delete();

        $this->sessionConsumables = array_values(
            array_filter($this->sessionConsumables, fn ($c) => $c['id'] !== $consumableId)
        );

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
        return Product::query()
            ->where('is_active', true)
            ->get();
    }

    public function addProduct(): void
    {
        if (!$this->newProductId || !$this->appointment) {
            return;
        }

        $product = Product::find($this->newProductId);
        if (!$product) {
            return;
        }

        $sessionProduct = SessionProduct::create([
            'tenant_id' => $this->appointment->tenant_id,
            'appointment_id' => $this->appointment->id,
            'product_id' => $product->id,
            'branch_id' => $this->appointment->branch_id,
            'quantity' => $this->newProductQty ?? 1,
            'unit' => $product->unit,
            'unit_price_minor' => $product->sell_price_minor,
            'usage_type' => $this->newProductUsageType ?? 'applied',
            'created_by' => auth()->id(),
        ]);

        $this->sessionProducts[] = [
            'id' => $sessionProduct->id,
            'product_id' => $sessionProduct->product_id,
            'product_name' => $product->getTranslation('name', app()->getLocale()),
            'quantity' => $sessionProduct->quantity,
            'unit' => $sessionProduct->unit,
            'unit_price' => $sessionProduct->unit_price,
            'total_price' => $sessionProduct->total_price,
            'usage_type' => $sessionProduct->usage_type,
        ];

        $this->newProductId = null;
        $this->newProductQty = 1;
        $this->newProductUsageType = 'applied';

        Notification::make()
            ->title(__('booking::session.messages.product_added'))
            ->success()
            ->send();
    }

    public function removeProduct(string $productId): void
    {
        SessionProduct::where('id', $productId)->delete();

        $this->sessionProducts = array_values(
            array_filter($this->sessionProducts, fn ($p) => $p['id'] !== $productId)
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
        if (!$this->appointment) {
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
        if (!$this->appointment || empty($this->prescriptionMedications)) {
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
                        'duration' => !empty($med['duration']) ? (int) $med['duration'] : null,
                        'duration_unit' => $med['duration_unit'] ?: null,
                        'quantity' => !empty($med['quantity']) ? (int) $med['quantity'] : null,
                        'route' => $med['route'] ?: null,
                        'instructions' => $med['instructions'] ?: null,
                        'special_instructions' => $med['special_instructions'] ?: null,
                        'sort_order' => $index,
                        'refills_allowed' => !empty($med['refills_allowed']) ? (int) $med['refills_allowed'] : 0,
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

        if (!$medicine) {
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
     * Set item price based on selection.
     */
    public function setItemPrice(Forms\Set $set, $itemId, string $type): void
    {
        if (!$itemId) {
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
        if (!$this->patient) {
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
                    if (!$plan) {
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

                    // Calculate final price with discount
                    if ($discountType === 'percent') {
                        $finalPrice = $originalPrice * (1 - $discountValue / 100);
                    } elseif ($discountType === 'fixed') {
                        $finalPrice = max(0, $originalPrice - $discountValue);
                    } else {
                        $finalPrice = $originalPrice;
                    }

                    $unitPriceMinor = (int) round($finalPrice * 100);

                    $itemData = [
                        'tenant_id' => $plan->tenant_id,
                        'treatment_plan_id' => $plan->id,
                        'item_type' => $itemType,
                        'unit_price_minor' => $unitPriceMinor,
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
}
