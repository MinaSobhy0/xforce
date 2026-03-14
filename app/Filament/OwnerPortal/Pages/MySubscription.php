<?php

namespace App\Filament\OwnerPortal\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use App\Models\SubscriptionPlan;
use App\Models\AddOn;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class MySubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'My Subscription';

    protected static string $view = 'filament.owner-portal.pages.my-subscription';

    #[Url]
    public ?string $action = null;

    public function mount(): void
    {
        // Auto-trigger action from URL parameter
        if ($this->action) {
            $this->mountAction($this->action);
            $this->action = null;
        }
    }

    public function getSubscriptionData(): array
    {
        $user = Auth::user();
        $tenant = $user?->tenant;
        $plan = $tenant?->plan;

        // Compute actual usage from tenant database
        $tenantUsage = $tenant?->computeUsage();

        return [
            'tenant' => $tenant,
            'plan' => $plan,
            'add_ons' => $tenant?->activeAddOns ?? collect(),
            'usage' => [
                'users' => $tenantUsage?->users ?? 0,
                'max_users' => $tenant?->getEffectiveLimit('users') ?? '∞',
                'patients' => $tenantUsage?->patients ?? 0,
                'max_patients' => $tenant?->getEffectiveLimit('patients') ?? '∞',
                'storage_mb' => $tenantUsage?->storage_mb ?? 0,
                'max_storage_mb' => $tenant?->getEffectiveLimit('storage_mb') ?? '∞',
                'branches' => $tenantUsage?->branches ?? 0,
                'max_branches' => $tenant?->getEffectiveLimit('branches') ?? '∞',
            ],
        ];
    }

    public function getAvailablePlans()
    {
        return SubscriptionPlan::whereRaw('is_active = true')
            ->orderBy('price_monthly_minor')
            ->get();
    }

    public function getAvailableAddOns()
    {
        return AddOn::whereRaw('is_active = true')
            ->orderBy('sort_order')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestUpgrade')
                ->label('Request Plan Upgrade')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('primary')
                ->modalWidth('lg')
                ->form(function () {
                    $tenant = Auth::user()->tenant;
                    $country = $tenant?->country ?? 'EG';
                    $currency = $tenant?->getCurrency() ?? 'EGP';

                    $plans = SubscriptionPlan::whereRaw('is_active = true')
                        ->orderBy('price_monthly_minor')
                        ->get();

                    return [
                        Radio::make('requested_plan_id')
                            ->label('Select Plan')
                            ->options(
                                $plans->mapWithKeys(fn($plan) => [
                                    $plan->id => $plan->name
                                ])
                            )
                            ->descriptions(
                                $plans->mapWithKeys(fn($plan) => [
                                    $plan->id => ($plan->max_users ?? '∞') . ' Users, '
                                        . ($plan->max_branches ?? '∞') . ' Branches — '
                                        . $currency . ' ' . number_format($plan->price_monthly_minor / 100) . '/mo'
                                ])
                            )
                            ->required(),
                        Textarea::make('message')
                            ->label('Additional Message (Optional)')
                            ->placeholder('Any specific requirements or questions...')
                            ->rows(3),
                    ];
                })
                ->action(function (array $data) {
                    $tenant = Auth::user()->tenant;
                    $plan = SubscriptionPlan::find($data['requested_plan_id']);

                    SupportTicket::create([
                        'tenant_id' => $tenant->id,
                        'ticket_number' => 'TKT-' . strtoupper(uniqid()),
                        'subject' => 'Plan Upgrade Request: ' . $plan->name,
                        'description' => "Clinic: {$tenant->name}\n\nRequested Plan: {$plan->name}\n\nMessage: " . ($data['message'] ?? 'No additional message'),
                        'category' => 'billing',
                        'priority' => 'normal',
                        'status' => 'open',
                    ]);

                    Notification::make()
                        ->title('Upgrade Request Submitted')
                        ->body('Our team will review your request and contact you shortly.')
                        ->success()
                        ->send();
                }),

            Action::make('requestAddOn')
                ->label('Request Add-On')
                ->icon('heroicon-o-plus-circle')
                ->color('gray')
                ->modalWidth('2xl')
                ->form(function () {
                    $tenant = Auth::user()->tenant;
                    $country = $tenant?->country ?? 'EG';

                    $addons = AddOn::whereRaw('is_active = true')
                        ->orderBy('sort_order')
                        ->get();

                    return [
                        CheckboxList::make('requested_addons')
                            ->label('Select Add-Ons')
                            ->options(
                                $addons->mapWithKeys(fn($addon) => [
                                    $addon->id => $addon->localizedName
                                ])
                            )
                            ->descriptions(
                                $addons->mapWithKeys(fn($addon) => [
                                    $addon->id => $addon->localizedDescription
                                        ? $addon->localizedDescription . ' — ' . $addon->getFormattedPriceForCountry($country) . '/mo'
                                        : $addon->getFormattedPriceForCountry($country) . '/mo'
                                ])
                            )
                            ->columns(2)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->required(),
                        Textarea::make('message')
                            ->label('Additional Message (Optional)')
                            ->rows(3),
                    ];
                })
                ->action(function (array $data) {
                    $tenant = Auth::user()->tenant;
                    $country = $tenant->country ?? 'EG';
                    $currency = $tenant->getCurrency();

                    $addonsData = AddOn::whereIn('id', $data['requested_addons'])->get();
                    $addonsList = $addonsData->map(function ($addon) use ($country) {
                        $price = $addon->getPriceForCountry($country);
                        return "- {$addon->name}: {$price['currency']} " . number_format($price['amount'], 2) . "/month";
                    })->join("\n");

                    $totalMonthly = $addonsData->sum(fn ($addon) => $addon->getPriceForCountry($country)['amount']);

                    SupportTicket::create([
                        'tenant_id' => $tenant->id,
                        'ticket_number' => 'TKT-' . strtoupper(uniqid()),
                        'subject' => 'Add-On Request: ' . $addonsData->pluck('name')->join(', '),
                        'description' => "Clinic: {$tenant->name}\nCountry: {$country}\n\nRequested Add-Ons:\n{$addonsList}\n\nEstimated Total: {$currency} " . number_format($totalMonthly, 2) . "/month\n\nMessage: " . ($data['message'] ?? 'No additional message'),
                        'category' => 'billing',
                        'priority' => 'normal',
                        'status' => 'open',
                    ]);

                    Notification::make()
                        ->title('Add-On Request Submitted')
                        ->body('Our team will review your request and contact you shortly.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
