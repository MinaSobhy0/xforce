<?php

namespace Modules\Booking\Livewire;

use App\Services\BranchContext;
use Livewire\Component;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Models\Package;
use Modules\TreatmentPlans\Services\TreatmentPlanService;
use Illuminate\Support\Collection;

class PatientPackages extends Component
{
    public ?string $patientId = null;
    public ?string $serviceId = null;
    public ?string $selectedSubscriptionId = null;
    public bool $showPurchaseForm = false;
    public ?string $newPackageId = null;

    public function mount(
        ?string $patientId = null,
        ?string $serviceId = null
    ): void {
        $this->patientId = $patientId;
        $this->serviceId = $serviceId;
    }

    public function getActivePackages(): Collection
    {
        if (!$this->patientId) {
            return collect();
        }

        $query = PackageSubscription::query()
            ->forPatient($this->patientId)
            ->active()
            ->with('package.items.service');

        // Filter by service if specified
        if ($this->serviceId) {
            $query->forService($this->serviceId);
        }

        return $query->get();
    }

    public function getAvailablePackages(): Collection
    {
        $query = Package::query()->active()->ordered();

        // Filter by service if specified
        if ($this->serviceId) {
            $query->whereHas('items', function ($q) {
                $q->where('service_id', $this->serviceId);
            });
        }

        return $query->get();
    }

    public function selectPackage(string $subscriptionId): void
    {
        $this->selectedSubscriptionId = $subscriptionId;
        $this->dispatch('package-selected', subscriptionId: $subscriptionId);
    }

    public function togglePurchaseForm(): void
    {
        $this->showPurchaseForm = !$this->showPurchaseForm;
    }

    public function purchasePackage(): void
    {
        if (!$this->patientId || !$this->newPackageId) {
            return;
        }

        $package = Package::with('items')->find($this->newPackageId);
        if (!$package) {
            return;
        }

        // Calculate effective price from package items
        $priceMinor = $package->effective_price_minor;

        $subscription = PackageSubscription::create([
            'patient_id' => $this->patientId,
            'package_id' => $this->newPackageId,
            'branch_id' => BranchContext::currentId(),
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

        // Auto-create treatment plan from package subscription
        try {
            $treatmentPlanService = app(TreatmentPlanService::class);
            $treatmentPlanService->createFromPackageSubscription($subscription);
        } catch (\Exception $e) {
            // Log error but don't fail the purchase
            \Log::error('Failed to create treatment plan from package: ' . $e->getMessage());
        }

        $this->selectedSubscriptionId = $subscription->id;
        $this->showPurchaseForm = false;
        $this->newPackageId = null;

        $this->dispatch('package-purchased', subscriptionId: $subscription->id);
        $this->dispatch('package-selected', subscriptionId: $subscription->id);
    }

    public function render()
    {
        return view('booking::livewire.booking.patient-packages', [
            'activePackages' => $this->getActivePackages(),
            'availablePackages' => $this->getAvailablePackages(),
        ]);
    }
}
