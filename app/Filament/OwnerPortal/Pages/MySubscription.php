<?php

namespace App\Filament\OwnerPortal\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use App\Models\SubscriptionPlan;
use App\Models\AddOn;
use App\Models\SupportTicket;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Auth;

class MySubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'My Subscription';

    protected static string $view = 'filament.owner-portal.pages.my-subscription';

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
            Action::make('requestAdditionalUsers')
                ->label('Buy Additional Users')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->form(function () {
                    $tenant = Auth::user()->tenant;
                    $pricePerUser = $tenant?->getExtraResourcePrice('user') ?? 50;
                    $currency = $tenant?->getCurrency() ?? 'EGP';
                    return [
                        TextInput::make('additional_users')
                            ->label('Number of Additional Users')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(1)
                            ->required()
                            ->helperText("Each additional user costs {$currency} {$pricePerUser}/month"),
                        Textarea::make('message')
                            ->label('Additional Message (Optional)')
                            ->placeholder('Any specific requirements...'),
                    ];
                })
                ->action(function (array $data) {
                    $tenant = Auth::user()->tenant;
                    $count = $data['additional_users'];
                    $pricePerUser = $tenant->getExtraResourcePrice('user');
                    $currency = $tenant->getCurrency();
                    $totalCost = $count * $pricePerUser;

                    SupportTicket::create([
                        'tenant_id' => $tenant->id,
                        'ticket_number' => 'TKT-' . strtoupper(uniqid()),
                        'subject' => "Request for {$count} Additional User(s)",
                        'description' => "Clinic: {$tenant->name}\nCountry: {$tenant->country}\n\nRequested Additional Users: {$count}\nPrice per User: {$currency} {$pricePerUser}/month\nEstimated Total: {$currency} " . number_format($totalCost) . "/month\n\nMessage: " . ($data['message'] ?? 'No additional message'),
                        'category' => 'billing',
                        'priority' => 'normal',
                        'status' => 'open',
                    ]);

                    Notification::make()
                        ->title('Request Submitted')
                        ->body("Your request for {$count} additional user(s) has been submitted. Our team will contact you shortly.")
                        ->success()
                        ->send();
                }),

            Action::make('requestUpgrade')
                ->label('Request Plan Upgrade')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('primary')
                ->form([
                    Select::make('requested_plan_id')
                        ->label('Select Plan')
                        ->options(
                            SubscriptionPlan::whereRaw('is_active = true')
                                ->orderBy('price_monthly_minor')
                                ->pluck('name', 'id')
                        )
                        ->required(),
                    Textarea::make('message')
                        ->label('Additional Message (Optional)')
                        ->placeholder('Any specific requirements or questions...'),
                ])
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
                ->form(function () {
                    $tenant = Auth::user()->tenant;
                    $country = $tenant?->country ?? 'EG';

                    return [
                        CheckboxList::make('requested_addons')
                            ->label('Select Add-Ons')
                            ->options(
                                AddOn::whereRaw('is_active = true')
                                    ->get()
                                    ->mapWithKeys(fn($addon) => [
                                        $addon->id => $addon->name . ' - ' . $addon->getFormattedPriceForCountry($country) . '/mo'
                                    ])
                            )
                            ->required(),
                        Textarea::make('message')
                            ->label('Additional Message (Optional)'),
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
