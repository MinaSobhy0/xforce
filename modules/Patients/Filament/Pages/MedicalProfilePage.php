<?php

namespace Modules\Patients\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Modules\Patients\Models\Patient;
use Modules\Patients\Models\MedicalProfile;
use Modules\Patients\Models\MedicalAllergy;
use Modules\Patients\Models\MedicalMedication;
use Modules\Patients\Models\MedicalContraindication;
use Modules\Patients\Models\MedicalHistory;
use Modules\Patients\Models\SkinAssessment;
use Modules\Patients\Models\LifestyleInfo;

class MedicalProfilePage extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'patients';
    protected static ?string $permissionKey = 'patients';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Patient Care';
    protected static ?int $navigationSort = 20;
    protected static ?string $slug = 'medical-profile';
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'patients::filament.pages.medical-profile';

    public static function getNavigationLabel(): string
    {
        return __('patients::medical_profile.navigation_label');
    }

    #[Url]
    public ?string $patient_id = null;

    public ?Patient $patient = null;
    public ?MedicalProfile $profile = null;

    // Profile form data
    public ?string $blood_type = null;
    public ?bool $is_pregnant = false;
    public ?bool $is_breastfeeding = false;
    public ?int $fitzpatrick_type = null;

    // Active tab
    public string $activeTab = 'overview';

    // Form data for adding items
    public array $allergyData = [];
    public array $medicationData = [];
    public array $contraindicationData = [];
    public array $historyData = [];
    public array $skinAssessmentData = [];
    public array $lifestyleData = [];

    public function mount(): void
    {
        if (!$this->patient_id) {
            Notification::make()
                ->title(__('patients::medical_profile.messages.patient_required'))
                ->danger()
                ->send();
            $this->redirect('/patients');
            return;
        }

        $this->patient = Patient::with(['medicalProfile'])->find($this->patient_id);

        if (!$this->patient) {
            Notification::make()
                ->title(__('patients::medical_profile.messages.patient_not_found'))
                ->danger()
                ->send();
            $this->redirect('/patients');
            return;
        }

        // Get or create medical profile
        $this->profile = MedicalProfile::getOrCreateForPatient($this->patient_id);

        // Load profile data
        $this->blood_type = $this->profile->blood_type;
        $this->is_pregnant = $this->profile->is_pregnant ?? false;
        $this->is_breastfeeding = $this->profile->is_breastfeeding ?? false;
        $this->fitzpatrick_type = $this->profile->fitzpatrick_type;

        // Load lifestyle data
        if ($this->profile->lifestyleInfo) {
            $this->lifestyleData = $this->profile->lifestyleInfo->toArray();
        }

        // Initialize form data
        $this->resetAllergyForm();
        $this->resetMedicationForm();
        $this->resetContraindicationForm();
        $this->resetHistoryForm();
        $this->resetSkinAssessmentForm();
    }

    public function getTitle(): string
    {
        return __('patients::medical_profile.title');
    }

    public function getHeading(): string
    {
        if ($this->patient) {
            return $this->patient->full_name . ' - ' . __('patients::medical_profile.heading');
        }
        return __('patients::medical_profile.heading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('patients::medical_profile.actions.back_to_patient'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => route('filament.tenant.resources.patients.view', ['record' => $this->patient_id])),

            Action::make('mark_reviewed')
                ->label(__('patients::medical_profile.actions.mark_reviewed'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->markAsReviewed())
                ->visible(fn () => $this->profile?->needsReview()),
        ];
    }

    // ============================================
    // PROFILE METHODS
    // ============================================

    public function saveProfile(): void
    {
        $this->profile->update([
            'blood_type' => $this->blood_type,
            'is_pregnant' => $this->is_pregnant,
            'is_breastfeeding' => $this->is_breastfeeding,
            'fitzpatrick_type' => $this->fitzpatrick_type,
            'updated_by' => auth()->id(),
        ]);

        Notification::make()
            ->title(__('patients::medical_profile.messages.profile_saved'))
            ->success()
            ->send();
    }

    public function markAsReviewed(): void
    {
        $this->profile->markAsReviewed();

        Notification::make()
            ->title(__('patients::medical_profile.messages.marked_reviewed'))
            ->success()
            ->send();
    }

    // ============================================
    // ALLERGY METHODS
    // ============================================

    public function resetAllergyForm(): void
    {
        $this->allergyData = [
            'allergy_type' => 'drug',
            'allergen' => '',
            'severity' => 'mild',
            'reaction' => '',
            'is_confirmed' => false,
            'show_alert' => true,
        ];
    }

    public function addAllergy(): void
    {
        $this->validate([
            'allergyData.allergen' => 'required|string|max:255',
            'allergyData.allergy_type' => 'required|in:drug,food,environmental,topical,metal,latex,other',
            'allergyData.severity' => 'required|in:mild,moderate,severe,life_threatening',
        ]);

        MedicalAllergy::create([
            'tenant_id' => $this->profile->tenant_id,
            'medical_profile_id' => $this->profile->id,
            'allergy_type' => $this->allergyData['allergy_type'],
            'allergen' => $this->allergyData['allergen'],
            'severity' => $this->allergyData['severity'],
            'reaction' => $this->allergyData['reaction'] ?? null,
            'is_confirmed' => $this->allergyData['is_confirmed'] ?? false,
            'show_alert' => $this->allergyData['show_alert'] ?? true,
        ]);

        $this->resetAllergyForm();
        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.allergy_added'))
            ->success()
            ->send();
    }

    public function deleteAllergy(string $id): void
    {
        MedicalAllergy::where('id', $id)
            ->where('medical_profile_id', $this->profile->id)
            ->delete();

        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.allergy_deleted'))
            ->success()
            ->send();
    }

    public function getAllergies(): Collection
    {
        return $this->profile?->allergies ?? collect();
    }

    // ============================================
    // MEDICATION METHODS
    // ============================================

    public function resetMedicationForm(): void
    {
        $this->medicationData = [
            'medication_name' => '',
            'dosage' => '',
            'frequency' => '',
            'reason' => '',
            'is_ongoing' => true,
            'affects_treatment' => false,
        ];
    }

    public function addMedication(): void
    {
        $this->validate([
            'medicationData.medication_name' => 'required|string|max:255',
        ]);

        MedicalMedication::create([
            'tenant_id' => $this->profile->tenant_id,
            'medical_profile_id' => $this->profile->id,
            'medication_name' => $this->medicationData['medication_name'],
            'dosage' => $this->medicationData['dosage'] ?? null,
            'frequency' => $this->medicationData['frequency'] ?? null,
            'reason' => $this->medicationData['reason'] ?? null,
            'is_ongoing' => $this->medicationData['is_ongoing'] ?? true,
            'affects_treatment' => $this->medicationData['affects_treatment'] ?? false,
            'start_date' => now(),
        ]);

        $this->resetMedicationForm();
        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.medication_added'))
            ->success()
            ->send();
    }

    public function deleteMedication(string $id): void
    {
        MedicalMedication::where('id', $id)
            ->where('medical_profile_id', $this->profile->id)
            ->delete();

        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.medication_deleted'))
            ->success()
            ->send();
    }

    public function getMedications(): Collection
    {
        return $this->profile?->medications ?? collect();
    }

    // ============================================
    // CONTRAINDICATION METHODS
    // ============================================

    public function resetContraindicationForm(): void
    {
        $this->contraindicationData = [
            'contraindication_type' => 'relative',
            'name' => '',
            'description' => '',
            'is_active' => true,
            'show_booking_alert' => true,
            'block_booking' => false,
        ];
    }

    public function addContraindication(): void
    {
        $this->validate([
            'contraindicationData.name' => 'required|string|max:255',
            'contraindicationData.contraindication_type' => 'required|in:absolute,relative,temporary',
        ]);

        MedicalContraindication::create([
            'tenant_id' => $this->profile->tenant_id,
            'medical_profile_id' => $this->profile->id,
            'contraindication_type' => $this->contraindicationData['contraindication_type'],
            'name' => $this->contraindicationData['name'],
            'description' => $this->contraindicationData['description'] ?? null,
            'is_active' => $this->contraindicationData['is_active'] ?? true,
            'show_booking_alert' => $this->contraindicationData['show_booking_alert'] ?? true,
            'block_booking' => $this->contraindicationData['block_booking'] ?? false,
            'source' => 'doctor_identified',
            'identified_by' => auth()->id(),
            'identified_at' => now(),
        ]);

        $this->resetContraindicationForm();
        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.contraindication_added'))
            ->success()
            ->send();
    }

    public function deleteContraindication(string $id): void
    {
        MedicalContraindication::where('id', $id)
            ->where('medical_profile_id', $this->profile->id)
            ->delete();

        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.contraindication_deleted'))
            ->success()
            ->send();
    }

    public function getContraindications(): Collection
    {
        return $this->profile?->contraindications ?? collect();
    }

    // ============================================
    // MEDICAL HISTORY METHODS
    // ============================================

    public function resetHistoryForm(): void
    {
        $this->historyData = [
            'history_type' => 'medical_condition',
            'name' => '',
            'description' => '',
            'is_ongoing' => false,
            'affects_treatment' => false,
        ];
    }

    public function addHistory(): void
    {
        $this->validate([
            'historyData.name' => 'required|string|max:255',
            'historyData.history_type' => 'required|in:medical_condition,surgery,hospitalization,family_history,social_history',
        ]);

        MedicalHistory::create([
            'tenant_id' => $this->profile->tenant_id,
            'medical_profile_id' => $this->profile->id,
            'history_type' => $this->historyData['history_type'],
            'name' => $this->historyData['name'],
            'description' => $this->historyData['description'] ?? null,
            'is_ongoing' => $this->historyData['is_ongoing'] ?? false,
            'affects_treatment' => $this->historyData['affects_treatment'] ?? false,
        ]);

        $this->resetHistoryForm();
        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.history_added'))
            ->success()
            ->send();
    }

    public function deleteHistory(string $id): void
    {
        MedicalHistory::where('id', $id)
            ->where('medical_profile_id', $this->profile->id)
            ->delete();

        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.history_deleted'))
            ->success()
            ->send();
    }

    public function getMedicalHistories(): Collection
    {
        return $this->profile?->medicalHistories ?? collect();
    }

    // ============================================
    // SKIN ASSESSMENT METHODS
    // ============================================

    public function resetSkinAssessmentForm(): void
    {
        $this->skinAssessmentData = [
            'fitzpatrick_type' => $this->profile?->fitzpatrick_type ?? null,
            'skin_type_oily' => '',
            'skin_sensitivity' => '',
            'skin_texture' => '',
            'pore_size' => '',
            'skin_tone' => '',
            'aging_level' => '',
            'sun_damage_level' => '',
            'current_conditions' => [],
            'aging_signs' => [],
            'areas_of_concern' => [],
            'clinical_observations' => '',
            'recommendations' => '',
        ];
    }

    public function addSkinAssessment(): void
    {
        $this->validate([
            'skinAssessmentData.fitzpatrick_type' => 'required|integer|min:1|max:6',
            'skinAssessmentData.skin_type_oily' => 'required|string',
            'skinAssessmentData.skin_sensitivity' => 'required|string',
        ]);

        // Helper to convert empty strings to null
        $nullIfEmpty = fn($value) => $value === '' || $value === null ? null : $value;

        SkinAssessment::create([
            'tenant_id' => $this->profile->tenant_id,
            'medical_profile_id' => $this->profile->id,
            'assessed_by' => auth()->id(),
            'fitzpatrick_type' => $this->skinAssessmentData['fitzpatrick_type'],
            'skin_type_oily' => $this->skinAssessmentData['skin_type_oily'],
            'skin_sensitivity' => $this->skinAssessmentData['skin_sensitivity'],
            'skin_texture' => $nullIfEmpty($this->skinAssessmentData['skin_texture'] ?? null),
            'pore_size' => $nullIfEmpty($this->skinAssessmentData['pore_size'] ?? null),
            'skin_tone' => $nullIfEmpty($this->skinAssessmentData['skin_tone'] ?? null),
            'aging_level' => $nullIfEmpty($this->skinAssessmentData['aging_level'] ?? null),
            'sun_damage_level' => $nullIfEmpty($this->skinAssessmentData['sun_damage_level'] ?? null),
            'current_conditions' => $this->skinAssessmentData['current_conditions'] ?? [],
            'aging_signs' => $this->skinAssessmentData['aging_signs'] ?? [],
            'areas_of_concern' => $this->skinAssessmentData['areas_of_concern'] ?? [],
            'clinical_observations' => $nullIfEmpty($this->skinAssessmentData['clinical_observations'] ?? null),
            'recommendations' => $nullIfEmpty($this->skinAssessmentData['recommendations'] ?? null),
        ]);

        // Also update the profile's fitzpatrick_type
        if ($this->skinAssessmentData['fitzpatrick_type']) {
            $this->profile->update([
                'fitzpatrick_type' => $this->skinAssessmentData['fitzpatrick_type'],
                'updated_by' => auth()->id(),
            ]);
            $this->fitzpatrick_type = $this->skinAssessmentData['fitzpatrick_type'];
        }

        $this->resetSkinAssessmentForm();
        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.skin_assessment_added'))
            ->success()
            ->send();
    }

    public function deleteSkinAssessment(string $id): void
    {
        SkinAssessment::where('id', $id)
            ->where('medical_profile_id', $this->profile->id)
            ->delete();

        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.skin_assessment_deleted'))
            ->success()
            ->send();
    }

    public function getSkinAssessments(): Collection
    {
        return $this->profile?->skinAssessments()->orderByDesc('created_at')->get() ?? collect();
    }

    public function getLatestSkinAssessment(): ?SkinAssessment
    {
        return $this->profile?->getLatestSkinAssessment();
    }

    // ============================================
    // LIFESTYLE METHODS
    // ============================================

    public function saveLifestyle(): void
    {
        if (!$this->profile->lifestyleInfo) {
            LifestyleInfo::create([
                'tenant_id' => $this->profile->tenant_id,
                'medical_profile_id' => $this->profile->id,
                ...$this->lifestyleData,
            ]);
        } else {
            $this->profile->lifestyleInfo->update($this->lifestyleData);
        }

        $this->profile->refresh();

        Notification::make()
            ->title(__('patients::medical_profile.messages.lifestyle_saved'))
            ->success()
            ->send();
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function hasAlerts(): bool
    {
        return $this->profile?->hasAlerts() ?? false;
    }

    public function getAlertCount(): int
    {
        $count = 0;
        if ($this->profile) {
            $count += $this->profile->allergies()->critical()->count();
            $count += $this->profile->contraindications()->active()->count();
            if ($this->profile->is_pregnant) $count++;
            if ($this->profile->is_breastfeeding) $count++;
        }
        return $count;
    }
}
