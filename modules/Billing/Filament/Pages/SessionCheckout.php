<?php

namespace Modules\Billing\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\InvoiceCalculationService;
use Modules\Booking\Models\SessionProduct;
use Modules\Accounting\Models\Journal;

class SessionCheckout extends Page implements HasForms, HasActions
{
    use ChecksResourcePermissions;
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $moduleCode = 'billing';
    protected static ?string $permissionKey = 'invoices';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $slug = 'session-checkout';
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'billing::filament.pages.session-checkout';

    #[Url]
    public ?string $invoice_id = null;

    public ?Invoice $invoice = null;
    public array $serviceLines = [];
    public array $productLines = [];
    public array $selectedProducts = [];
    public array $payments = [];
    public int $selectedTotal = 0;
    public int $paymentsTotal = 0;

    public function mount(): void
    {
        if ($this->invoice_id) {
            $this->invoice = Invoice::with(['patient', 'lines.service', 'lines.product', 'appointment'])
                ->find($this->invoice_id);
        }

        if (!$this->invoice) {
            $this->redirect('/');
            return;
        }

        $this->loadInvoiceLines();
        $this->initializePayments();
        $this->calculateTotals();
    }

    public static function getNavigationLabel(): string
    {
        return __('billing::checkout.navigation');
    }

    public function getTitle(): string
    {
        return __('billing::checkout.title');
    }

    public function getHeading(): string
    {
        return __('billing::checkout.heading', [
            'patient' => $this->invoice?->patient?->full_name ?? '',
            'code' => $this->invoice?->code ?? '',
        ]);
    }

    protected function loadInvoiceLines(): void
    {
        $this->serviceLines = $this->invoice->lines()
            ->where('line_type', InvoiceLine::LINE_TYPE_SERVICE)
            ->get()
            ->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price_minor / 100,
                'discount' => $line->discount_minor / 100,
                'total' => $line->total_minor / 100,
                'total_minor' => $line->total_minor,
                'selected' => true, // Services always selected
            ])
            ->toArray();

        $this->productLines = $this->invoice->lines()
            ->where('line_type', InvoiceLine::LINE_TYPE_PRODUCT)
            ->get()
            ->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price_minor / 100,
                'total' => $line->total_minor / 100,
                'total_minor' => $line->total_minor,
                'session_product_id' => $line->session_product_id,
                'product_id' => $line->product_id,
                'selected' => true,
            ])
            ->toArray();

        // Initialize selected products
        $this->selectedProducts = array_fill_keys(
            array_column($this->productLines, 'id'),
            true
        );
    }

    protected function initializePayments(): void
    {
        $this->payments = [
            [
                'journal_id' => null,
                'amount' => 0,
                'reference' => '',
            ]
        ];
    }

    public function calculateTotals(): void
    {
        // Services total (always included)
        $servicesTotal = array_sum(array_column($this->serviceLines, 'total_minor'));

        // Products total (only selected)
        $productsTotal = 0;
        foreach ($this->productLines as $line) {
            if ($this->selectedProducts[$line['id']] ?? false) {
                $productsTotal += $line['total_minor'];
            }
        }

        $this->selectedTotal = $servicesTotal + $productsTotal;

        // Payments total
        $this->paymentsTotal = (int) array_sum(array_map(
            fn ($p) => (int) (($p['amount'] ?? 0) * 100),
            $this->payments
        ));
    }

    public function toggleProduct(int $lineId): void
    {
        $this->selectedProducts[$lineId] = !($this->selectedProducts[$lineId] ?? false);
        $this->calculateTotals();
    }

    public function addPaymentMethod(): void
    {
        $this->payments[] = [
            'journal_id' => null,
            'amount' => 0,
            'reference' => '',
        ];
    }

    public function removePaymentMethod(int $index): void
    {
        if (count($this->payments) > 1) {
            unset($this->payments[$index]);
            $this->payments = array_values($this->payments);
            $this->calculateTotals();
        }
    }

    public function updatedPayments(): void
    {
        $this->calculateTotals();
    }

    public function payServicesOnly(): void
    {
        // Deselect all products
        foreach ($this->productLines as $line) {
            $this->selectedProducts[$line['id']] = false;
        }
        $this->calculateTotals();

        // Set first payment to services total
        if (!empty($this->payments)) {
            $this->payments[0]['amount'] = $this->selectedTotal / 100;
        }
    }

    public function payAll(): void
    {
        // Select all products
        foreach ($this->productLines as $line) {
            $this->selectedProducts[$line['id']] = true;
        }
        $this->calculateTotals();

        // Set first payment to total
        if (!empty($this->payments)) {
            $this->payments[0]['amount'] = $this->selectedTotal / 100;
        }
    }

    public function getAvailableJournals(): array
    {
        return Journal::where('is_payment_method', true)
            ->where('is_active', true)
            ->pluck('name', 'id')
            ->toArray();
    }

    public function completeCheckout(): void
    {
        // Validate payments total
        if ($this->paymentsTotal < $this->selectedTotal) {
            Notification::make()
                ->title(__('billing::checkout.messages.insufficient_payment'))
                ->body(__('billing::checkout.messages.insufficient_payment_body', [
                    'required' => number_format($this->selectedTotal / 100, 2),
                    'provided' => number_format($this->paymentsTotal / 100, 2),
                ]))
                ->danger()
                ->send();
            return;
        }

        // Validate at least one payment method selected
        $validPayments = array_filter($this->payments, fn ($p) => $p['journal_id'] && $p['amount'] > 0);
        if (empty($validPayments)) {
            Notification::make()
                ->title(__('billing::checkout.messages.no_payment_method'))
                ->danger()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($validPayments) {
                // Handle unselected products - remove from invoice and return to inventory
                foreach ($this->productLines as $line) {
                    if (!($this->selectedProducts[$line['id']] ?? false)) {
                        $this->cancelProductLine($line);
                    }
                }

                // Recalculate invoice totals
                $this->invoice->refresh();
                $newTotal = $this->invoice->lines()->sum('total_minor');
                $this->invoice->update([
                    'subtotal_minor' => $newTotal,
                    'total_minor' => $newTotal + ($this->invoice->tax_minor ?? 0) - ($this->invoice->discount_minor ?? 0),
                ]);

                // Create payments
                foreach ($validPayments as $paymentData) {
                    Payment::create([
                        'tenant_id' => $this->invoice->tenant_id,
                        'invoice_id' => $this->invoice->id,
                        'patient_id' => $this->invoice->patient_id,
                        'branch_id' => $this->invoice->branch_id,
                        'journal_id' => $paymentData['journal_id'],
                        'amount_minor' => (int) ($paymentData['amount'] * 100),
                        'reference' => $paymentData['reference'] ?? null,
                        'paid_at' => now(),
                        'status' => Payment::STATUS_COMPLETED,
                        'received_by_user_id' => auth()->id(),
                    ]);
                }

                // Update invoice paid amount and status
                $totalPaid = $this->invoice->payments()->sum('amount_minor');
                $this->invoice->update([
                    'paid_minor' => $totalPaid,
                    'status' => $totalPaid >= $this->invoice->total_minor
                        ? Invoice::STATUS_PAID
                        : Invoice::STATUS_PARTIALLY_PAID,
                    'paid_at' => $totalPaid >= $this->invoice->total_minor ? now() : null,
                ]);
            });

            Notification::make()
                ->title(__('billing::checkout.messages.checkout_complete'))
                ->success()
                ->send();

            // Redirect to reception dashboard
            $this->redirect(route('filament.tenant.pages.reception'));

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('billing::checkout.messages.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function cancelProductLine(array $line): void
    {
        // Find and delete the invoice line
        $invoiceLine = InvoiceLine::find($line['id']);
        if (!$invoiceLine) {
            return;
        }

        // Return product to inventory
        if ($line['session_product_id']) {
            $sessionProduct = SessionProduct::find($line['session_product_id']);
            if ($sessionProduct) {
                // Return to inventory
                $sessionProduct->returnToInventory();
                // Mark as not invoiced
                $sessionProduct->update([
                    'is_invoiced' => false,
                    'invoice_line_id' => null,
                ]);
            }
        }

        // Delete the invoice line
        $invoiceLine->delete();
    }

    public function getServicesTotal(): float
    {
        return array_sum(array_column($this->serviceLines, 'total_minor')) / 100;
    }

    public function getSelectedProductsTotal(): float
    {
        $total = 0;
        foreach ($this->productLines as $line) {
            if ($this->selectedProducts[$line['id']] ?? false) {
                $total += $line['total_minor'];
            }
        }
        return $total / 100;
    }

    public function isBalanced(): bool
    {
        return $this->paymentsTotal >= $this->selectedTotal;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('billing::checkout.actions.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(route('filament.tenant.pages.reception')),
        ];
    }
}
