<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Modules\Billing\Filament\Resources\InvoiceResource;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\Visit;
use Modules\Booking\Services\VisitService;
use Modules\Patients\Models\Patient;

class Checkout extends Page implements HasActions, HasForms
{
    use ChecksResourcePermissions;
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'checkout';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 16;

    protected static ?string $slug = 'checkout';

    protected static bool $shouldRegisterNavigation = false; // Accessed via visit link

    protected static string $view = 'booking::filament.pages.checkout';

    #[Url]
    public ?string $visit_id = null;

    public ?Visit $visit = null;

    public ?Patient $patient = null;

    // Session actions for open sessions
    public array $sessionActions = [];

    // Package payment options (package_id => 'full' or 'deposit')
    public array $packagePaymentOptions = [];

    // Discount settings
    public ?string $overallDiscountType = 'none';

    public ?float $overallDiscountValue = 0;

    public ?string $overallDiscountReason = null;

    // Computed values
    public int $subtotalMinor = 0;

    public int $discountMinor = 0;

    public int $lineDiscountsMinor = 0; // Discounts already applied to appointments/products

    public int $totalMinor = 0;

    public int $packagesSubtotalMinor = 0; // Full package prices

    public int $packagesPayableMinor = 0;  // Amount to pay (full or deposit)

    public static function getNavigationLabel(): string
    {
        return __('booking::checkout.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::checkout.title');
    }

    public function getHeading(): string
    {
        if ($this->visit) {
            return __('booking::checkout.heading', ['code' => $this->visit->code]);
        }

        return __('booking::checkout.title');
    }

    public function mount(): void
    {
        $this->loadVisit();

        if (! $this->visit) {
            Notification::make()
                ->title(__('booking::checkout.messages.visit_not_found'))
                ->danger()
                ->send();
            $this->redirect(ReceptionDashboard::getUrl());

            return;
        }

        // Check visit status
        if ($this->visit->status === Visit::STATUS_INVOICED) {
            Notification::make()
                ->title(__('booking::checkout.messages.already_invoiced'))
                ->warning()
                ->send();
            $this->redirect(InvoiceResource::getUrl('view', ['record' => $this->visit->invoice_id]));

            return;
        }

        if ($this->visit->status === Visit::STATUS_CANCELLED) {
            Notification::make()
                ->title(__('booking::checkout.messages.visit_cancelled'))
                ->danger()
                ->send();
            $this->redirect(ReceptionDashboard::getUrl());

            return;
        }

        $this->initializeSessionActions();
        $this->calculateTotals();
    }

    protected function loadVisit(): void
    {
        $this->visit = Visit::with([
            'patient',
            'appointments.service',
            'appointments.practitioner',
            'appointments.treatmentPlanAppointment.item',
            'products.product',
            'pendingPackages',
            'checkedInBy',
        ])->find($this->visit_id);

        if ($this->visit) {
            $this->patient = $this->visit->patient;
        }
    }

    protected function initializeSessionActions(): void
    {
        // Initialize actions for open sessions
        foreach ($this->getOpenAppointments() as $appointment) {
            $this->sessionActions[$appointment->id] = 'complete'; // Default to complete
        }

        // Initialize package payment options from pivot data
        foreach ($this->getPendingPackages() as $package) {
            $this->packagePaymentOptions[$package->id] = $package->pivot->payment_option ?? 'full';
        }
    }

    public function getOpenAppointments(): Collection
    {
        if (! $this->visit) {
            return collect();
        }

        return $this->visit->appointments->filter(function ($apt) {
            return in_array($apt->status, [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_IN_PROGRESS,
            ]);
        });
    }

    public function getCompletedAppointments(): Collection
    {
        if (! $this->visit) {
            return collect();
        }

        return $this->visit->appointments->where('status', Appointment::STATUS_COMPLETED);
    }

    public function getCancelledAppointments(): Collection
    {
        if (! $this->visit) {
            return collect();
        }

        return $this->visit->appointments->whereIn('status', [
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ]);
    }

    public function getSoldProducts(): Collection
    {
        if (! $this->visit) {
            return collect();
        }

        // Get products linked directly to the visit
        $visitProducts = $this->visit->products->where('usage_type', 'sold');

        // Also get products from appointments in this visit (for legacy data without visit_id)
        $appointmentIds = $this->visit->appointments->pluck('id');
        $appointmentProducts = \Modules\Booking\Models\SessionProduct::with('product')
            ->whereIn('appointment_id', $appointmentIds)
            ->where('usage_type', 'sold')
            ->whereNull('visit_id')
            ->get();

        return $visitProducts->merge($appointmentProducts)->unique('id');
    }

    public function getPendingPackages(): Collection
    {
        if (! $this->visit) {
            return collect();
        }

        return $this->visit->pendingPackages;
    }

    public function calculateTotals(): void
    {
        $servicesTotal = 0;
        $productsTotal = 0;
        $this->lineDiscountsMinor = 0;

        // Add completed appointments
        foreach ($this->getCompletedAppointments() as $apt) {
            if (! $apt->is_package_session) {
                $servicesTotal += $apt->net_price ?? $apt->price_minor ?? 0;
                // Use the model's method to get actual discount amount
                $this->lineDiscountsMinor += $apt->getDiscountAmountMinor();
            }
        }

        // Add open appointments that will be completed
        foreach ($this->getOpenAppointments() as $apt) {
            $action = $this->sessionActions[$apt->id] ?? 'complete';
            if ($action === 'complete' && ! $apt->is_package_session) {
                $servicesTotal += $apt->net_price ?? $apt->price_minor ?? 0;
                // Use the model's method to get actual discount amount
                $this->lineDiscountsMinor += $apt->getDiscountAmountMinor();
            }
        }

        // Add sold products
        foreach ($this->getSoldProducts() as $prod) {
            $productsTotal += $prod->total_price_minor ?? 0;
            // Calculate actual discount for products (unit_price * qty - total)
            $grossPrice = (int) (($prod->unit_price_minor ?? 0) * ($prod->quantity ?? 1));
            $this->lineDiscountsMinor += max(0, $grossPrice - ($prod->total_price_minor ?? 0));
        }

        // Calculate package totals
        $this->packagesSubtotalMinor = 0;
        $this->packagesPayableMinor = 0;

        foreach ($this->getPendingPackages() as $package) {
            $priceMinor = $package->pivot->package_price_minor;
            $this->packagesSubtotalMinor += $priceMinor;

            $paymentOption = $this->packagePaymentOptions[$package->id] ?? 'full';
            if ($paymentOption === 'full') {
                $this->packagesPayableMinor += $priceMinor;
            } else {
                // Deposit amount
                $depositPercent = $package->min_deposit_percent ?? 100;
                $this->packagesPayableMinor += (int) ceil($priceMinor * $depositPercent / 100);
            }
        }

        // Subtotal includes full package prices (for invoice line items)
        // Note: servicesTotal and productsTotal already have line discounts applied (net_price)
        $this->subtotalMinor = $servicesTotal + $productsTotal + $this->packagesSubtotalMinor;

        // Calculate discount on services + products only (not packages)
        $discountableAmount = $servicesTotal + $productsTotal;
        $this->discountMinor = $this->calculateOverallDiscount($discountableAmount);

        // Total = services + products - discount + packages payable
        $this->totalMinor = max(0, $servicesTotal + $productsTotal - $this->discountMinor + $this->packagesPayableMinor);
    }

    protected function calculateOverallDiscount(int $subtotal): int
    {
        if ($this->overallDiscountType === 'none' || ! $this->overallDiscountValue) {
            return 0;
        }

        // SECURITY: Ensure discount value is not negative
        $discountValue = max(0, (float) $this->overallDiscountValue);

        if ($this->overallDiscountType === 'percent') {
            // SECURITY: Cap percentage discount at 100% to prevent over-discounting
            $cappedPercent = min($discountValue, 100);
            return (int) round($subtotal * $cappedPercent / 100);
        }

        // Fixed amount (convert from major to minor), capped at subtotal
        return min((int) ($discountValue * 100), $subtotal);
    }

    public function updateSessionAction(int $appointmentId, string $action): void
    {
        $this->sessionActions[$appointmentId] = $action;
        $this->calculateTotals();
    }

    public function updatePackagePaymentOption(int $packageId, string $option): void
    {
        $this->packagePaymentOptions[$packageId] = $option;

        // Also update the pivot table
        if ($this->visit) {
            $this->visit->updatePendingPackagePaymentOption($packageId, $option);
        }

        $this->calculateTotals();
    }

    public function removePendingPackage(int $packageId): void
    {
        if ($this->visit) {
            $this->visit->pendingPackages()->detach($packageId);
            $this->loadVisit(); // Reload to refresh the relationship
        }

        unset($this->packagePaymentOptions[$packageId]);
        $this->calculateTotals();

        Notification::make()
            ->title(__('booking::checkout.messages.package_removed'))
            ->success()
            ->send();
    }

    public function applyDiscount(): void
    {
        // SECURITY: Validate discount value before applying
        if ($this->overallDiscountValue !== null) {
            $this->overallDiscountValue = max(0, (float) $this->overallDiscountValue);

            // Cap percentage discounts at 100%
            if ($this->overallDiscountType === 'percent' && $this->overallDiscountValue > 100) {
                $this->overallDiscountValue = 100;
                Notification::make()
                    ->title(__('booking::checkout.messages.discount_capped'))
                    ->body(__('booking::checkout.messages.discount_capped_body'))
                    ->warning()
                    ->send();
            }
        }

        $this->calculateTotals();

        Notification::make()
            ->title(__('booking::checkout.messages.discount_applied'))
            ->success()
            ->send();
    }

    public function removeDiscount(): void
    {
        $this->overallDiscountType = 'none';
        $this->overallDiscountValue = 0;
        $this->overallDiscountReason = null;
        $this->calculateTotals();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('booking::checkout.actions.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ReceptionDashboard::getUrl()),

            Action::make('viewPatient')
                ->label(__('booking::checkout.actions.view_patient'))
                ->icon('heroicon-o-user')
                ->color('gray')
                ->url(fn () => $this->patient
                    ? \Modules\Patients\Filament\Resources\PatientResource::getUrl('view', ['record' => $this->patient->id])
                    : '#'
                )
                ->visible(fn () => $this->patient !== null),
        ];
    }

    public function confirmCheckoutAction(): Action
    {
        return Action::make('confirmCheckout')
            ->label(__('booking::checkout.actions.confirm_checkout'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->size('lg')
            ->requiresConfirmation()
            ->modalHeading(__('booking::checkout.modals.confirm_checkout'))
            ->modalDescription(function () {
                $openCount = $this->getOpenAppointments()->count();
                $message = __('booking::checkout.modals.confirm_description', [
                    'total' => number_format($this->totalMinor / 100, 2),
                    'currency' => current_currency(),
                ]);

                if ($openCount > 0) {
                    $message .= "\n\n".__('booking::checkout.modals.open_sessions_warning', [
                        'count' => $openCount,
                    ]);
                }

                return $message;
            })
            ->modalSubmitActionLabel(__('booking::checkout.actions.generate_invoice'))
            ->action(function () {
                $this->processCheckout();
            });
    }

    public function processCheckout(): void
    {
        try {
            $visitService = app(VisitService::class);

            // Apply overall discount to visit before checkout
            if ($this->discountMinor > 0) {
                $this->visit->update([
                    'discount_minor' => $this->discountMinor,
                    'discount_type' => $this->overallDiscountType,
                    'discount_reason' => $this->overallDiscountReason,
                ]);
            }

            // Build discount info for invoice line distribution
            $discountInfo = [];
            if ($this->discountMinor > 0) {
                $discountInfo = [
                    'amount_minor' => $this->discountMinor,
                    'type' => $this->overallDiscountType,
                    'reason' => $this->overallDiscountReason ?? __('booking::checkout.invoice_notes.checkout_discount'),
                ];
            }

            // Process checkout with discount info
            $invoice = $visitService->checkout($this->visit, $this->sessionActions, $discountInfo);

            Notification::make()
                ->title(__('booking::checkout.messages.checkout_success'))
                ->body(__('booking::checkout.messages.invoice_created', ['code' => $invoice->code]))
                ->success()
                ->send();

            // Redirect to invoice
            $this->redirect(InvoiceResource::getUrl('view', ['record' => $invoice->id]));

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::checkout.messages.checkout_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getVisitDuration(): string
    {
        if (! $this->visit || ! $this->visit->check_in_at) {
            return '-';
        }

        $checkIn = $this->visit->check_in_at;
        $now = now();
        $diff = $checkIn->diff($now);

        if ($diff->h > 0) {
            return $diff->format('%hh %im');
        }

        return $diff->format('%im');
    }

    public function getPackageSessionsCount(): int
    {
        if (! $this->visit) {
            return 0;
        }

        return $this->visit->appointments
            ->where('is_package_session', true)
            ->count();
    }

    public function getPackageDeductionTotal(): int
    {
        if (! $this->visit) {
            return 0;
        }

        return $this->visit->appointments
            ->where('is_package_session', true)
            ->sum(fn ($apt) => $apt->net_price ?? $apt->price_minor ?? 0);
    }
}
