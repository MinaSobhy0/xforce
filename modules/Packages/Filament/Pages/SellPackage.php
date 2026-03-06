<?php

namespace Modules\Packages\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Modules\Core\Context\BranchContext;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Modules\Packages\Services\PackageService;
use Modules\Patients\Models\Patient;

class SellPackage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string $view = 'packages::pages.sell-package';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 11;

    public ?int $patient_id = null;
    public ?int $package_id = null;
    public ?int $deposit_amount = null;
    public string $activation_rule = PackageSubscription::ACTIVATION_IMMEDIATE;
    public string $payment_option = 'full';

    public ?Package $selectedPackage = null;

    public static function getNavigationLabel(): string
    {
        return __('packages::packages.pages.sell_package');
    }

    public function getTitle(): string
    {
        return __('packages::packages.pages.sell_package');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('packages::packages.sections.patient_selection'))
                    ->schema([
                        Forms\Components\Select::make('patient_id')
                            ->label(__('packages::packages.fields.patient'))
                            ->relationship('', 'full_name')
                            ->options(fn () => Patient::query()
                                ->where('is_active', true)
                                ->limit(100)
                                ->get()
                                ->mapWithKeys(fn ($patient) => [
                                    $patient->id => $patient->full_name . ' (' . $patient->phone . ')'
                                ]))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Patient::query()
                                ->where('is_active', true)
                                ->where(function ($q) use ($search) {
                                    $q->where('first_name', 'ilike', "%{$search}%")
                                        ->orWhere('last_name', 'ilike', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%");
                                })
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($patient) => [
                                    $patient->id => $patient->full_name . ' (' . $patient->phone . ')'
                                ]))
                            ->required()
                            ->live(),
                    ]),

                Forms\Components\Section::make(__('packages::packages.sections.package_selection'))
                    ->schema([
                        Forms\Components\Select::make('package_id')
                            ->label(__('packages::packages.fields.package'))
                            ->options(fn () => Package::active()
                                ->ordered()
                                ->with('items')
                                ->get()
                                ->mapWithKeys(fn ($package) => [
                                    $package->id => $package->translated_name .
                                        ' (' . $package->total_sessions . ' sessions) - ' .
                                        format_money($package->effective_price_minor)
                                ]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->updateSelectedPackage($state)),

                        Forms\Components\Placeholder::make('package_details')
                            ->label(__('packages::packages.sections.package_details'))
                            ->content(fn () => $this->getPackageDetailsContent())
                            ->visible(fn () => $this->selectedPackage !== null),
                    ]),

                Forms\Components\Section::make(__('packages::packages.sections.payment'))
                    ->schema([
                        Forms\Components\Radio::make('payment_option')
                            ->label(__('packages::packages.fields.payment_option'))
                            ->options([
                                'full' => __('packages::packages.payment_options.full'),
                                'deposit' => __('packages::packages.payment_options.deposit'),
                            ])
                            ->default('full')
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('deposit_amount')
                            ->label(__('packages::packages.fields.deposit_amount'))
                            ->numeric()
                            ->prefix(current_currency())
                            ->visible(fn (Forms\Get $get) => $get('payment_option') === 'deposit')
                            ->helperText(fn () => $this->selectedPackage
                                ? __('packages::packages.fields.min_deposit', [
                                    'amount' => format_money($this->selectedPackage->min_deposit_amount),
                                    'percent' => $this->selectedPackage->min_deposit_percent,
                                ])
                                : '')
                            ->rules([
                                fn () => function (string $attribute, $value, $fail) {
                                    if ($this->payment_option === 'deposit' && $this->selectedPackage) {
                                        $minDeposit = $this->selectedPackage->min_deposit_amount;
                                        $valueMinor = (int) ($value * 100);
                                        if ($valueMinor < $minDeposit) {
                                            $fail(__('packages::packages.validation.min_deposit', [
                                                'amount' => format_money($minDeposit),
                                            ]));
                                        }
                                    }
                                },
                            ]),

                        Forms\Components\Radio::make('activation_rule')
                            ->label(__('packages::packages.fields.activation_rule'))
                            ->options([
                                PackageSubscription::ACTIVATION_IMMEDIATE => __('packages::packages.activation_rules.immediate'),
                                PackageSubscription::ACTIVATION_PAID_IN_FULL => __('packages::packages.activation_rules.paid_in_full'),
                            ])
                            ->default(PackageSubscription::ACTIVATION_IMMEDIATE)
                            ->visible(fn (Forms\Get $get) => $get('payment_option') === 'deposit')
                            ->helperText(__('packages::packages.fields.activation_rule_help')),
                    ])
                    ->visible(fn () => $this->selectedPackage !== null),

                Forms\Components\Section::make(__('packages::packages.sections.summary'))
                    ->schema([
                        Forms\Components\Placeholder::make('summary')
                            ->content(fn () => $this->getSummaryContent())
                            ->columnSpanFull(),
                    ])
                    ->visible(fn () => $this->selectedPackage !== null && $this->patient_id !== null),
            ]);
    }

    public function updateSelectedPackage($packageId): void
    {
        $this->selectedPackage = $packageId
            ? Package::with(['items.service'])->find($packageId)
            : null;
    }

    protected function getPackageDetailsContent(): HtmlString
    {
        if (!$this->selectedPackage) {
            return new HtmlString('');
        }

        $package = $this->selectedPackage;
        $services = $package->items->map(function ($item) {
            $unitLabel = $item->isSessionBased() ? 'sessions' : 'pulses';
            $priceInfo = format_money($item->unit_price_minor) . '/session';
            $totalPrice = format_money($item->total_price_minor);
            return "<tr class='border-b'>
                <td class='py-1'>{$item->service->translated_name}</td>
                <td class='py-1 text-center'>{$item->quantity} {$unitLabel}</td>
                <td class='py-1 text-right'>{$priceInfo}</td>
                <td class='py-1 text-right font-medium'>{$totalPrice}</td>
            </tr>";
        })->implode('');

        $totalPrice = format_money($package->effective_price_minor);

        return new HtmlString("
            <div class='space-y-3'>
                <table class='w-full text-sm'>
                    <thead>
                        <tr class='border-b text-gray-500'>
                            <th class='py-1 text-left'>Service</th>
                            <th class='py-1 text-center'>Qty</th>
                            <th class='py-1 text-right'>Unit Price</th>
                            <th class='py-1 text-right'>Total</th>
                        </tr>
                    </thead>
                    <tbody>{$services}</tbody>
                    <tfoot>
                        <tr class='font-semibold'>
                            <td colspan='3' class='py-2 text-right'>Package Total:</td>
                            <td class='py-2 text-right'>{$totalPrice}</td>
                        </tr>
                    </tfoot>
                </table>
                <div class='flex gap-4 text-sm text-gray-600'>
                    <span><strong>Validity:</strong> {$package->validity_days} days</span>
                    <span><strong>Transferable:</strong> " . ($package->is_transferable ? 'Yes' : 'No') . "</span>
                </div>
            </div>
        ");
    }

    protected function getSummaryContent(): HtmlString
    {
        if (!$this->selectedPackage || !$this->patient_id) {
            return new HtmlString('');
        }

        $patient = Patient::find($this->patient_id);
        $package = $this->selectedPackage;

        $packagePrice = $package->effective_price_minor;
        $paymentAmount = $this->payment_option === 'full'
            ? $packagePrice
            : (int) (($this->deposit_amount ?? 0) * 100);

        $balance = $packagePrice - $paymentAmount;
        $activationText = $this->activation_rule === PackageSubscription::ACTIVATION_IMMEDIATE
            ? 'Immediate'
            : 'After full payment';

        return new HtmlString("
            <div class='space-y-2 text-sm'>
                <p><strong>Patient:</strong> {$patient->full_name}</p>
                <p><strong>Package:</strong> {$package->translated_name}</p>
                <p><strong>Package Price:</strong> " . format_money($packagePrice) . "</p>
                <p><strong>Payment Now:</strong> " . format_money($paymentAmount) . "</p>
                " . ($balance > 0 ? "<p><strong>Balance Due:</strong> " . format_money($balance) . "</p>" : "") . "
                <p><strong>Activation:</strong> {$activationText}</p>
                <p><strong>Expires:</strong> " . now()->addDays($package->validity_days)->format('M d, Y') . "</p>
            </div>
        ");
    }

    public function sellPackage(): void
    {
        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'package_id' => 'required|exists:packages,id',
        ]);

        $depositAmount = $this->payment_option === 'full'
            ? $this->selectedPackage->effective_price_minor
            : (int) (($this->deposit_amount ?? 0) * 100);

        try {
            $packageService = app(PackageService::class);

            $subscription = $packageService->sellPackage(
                patientId: $this->patient_id,
                packageId: $this->package_id,
                branchId: BranchContext::getCurrentBranchId() ?? 1,
                depositAmount: $depositAmount,
                activationRule: $this->activation_rule,
                createdByUserId: auth()->id()
            );

            Notification::make()
                ->title(__('packages::packages.notifications.package_sold'))
                ->body(__('packages::packages.notifications.package_sold_body', [
                    'package' => $subscription->package->translated_name,
                    'patient' => $subscription->patient->full_name,
                ]))
                ->success()
                ->send();

            // Redirect to the invoice or subscription view
            if ($subscription->invoice) {
                $this->redirect(route('filament.admin.resources.invoices.view', ['record' => $subscription->invoice_id]));
            } else {
                $this->reset(['patient_id', 'package_id', 'deposit_amount', 'selectedPackage']);
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('packages::packages.notifications.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function mount(): void
    {
        $this->form->fill();
    }
}
