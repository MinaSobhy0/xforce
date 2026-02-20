<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use App\Models\SubscriptionPlan;
use App\Models\AddOn;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;

class MySubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Subscription';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'My Subscription';

    protected static string $view = 'filament.admin.pages.my-subscription';

    public function getSubscriptionData(): array
    {
        $user = Auth::user();
        $tenant = $user?->tenant;
        $plan = $tenant?->plan;

        return [
            'tenant' => $tenant,
            'plan' => $plan,
            'add_ons' => $tenant?->addOns ?? collect(),
            'usage' => [
                'users' => $tenant?->users()->count() ?? 0,
                'max_users' => $plan?->max_users ?? '∞',
                'patients' => 0, // Would come from tenant data
                'max_patients' => $plan?->max_patients ?? '∞',
                'storage_mb' => $tenant?->storage_used_mb ?? 0,
                'max_storage_mb' => $plan?->max_storage_mb ?? '∞',
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
                ->form([
                    CheckboxList::make('requested_addons')
                        ->label('Select Add-Ons')
                        ->options(
                            AddOn::whereRaw('is_active = true')
                                ->get()
                                ->mapWithKeys(fn($addon) => [
                                    $addon->id => $addon->name . ' - EGP ' . number_format($addon->price_monthly_minor / 100) . '/mo'
                                ])
                        )
                        ->required(),
                    Textarea::make('message')
                        ->label('Additional Message (Optional)'),
                ])
                ->action(function (array $data) {
                    $tenant = Auth::user()->tenant;
                    $addons = AddOn::whereIn('id', $data['requested_addons'])->pluck('name')->join(', ');

                    SupportTicket::create([
                        'tenant_id' => $tenant->id,
                        'ticket_number' => 'TKT-' . strtoupper(uniqid()),
                        'subject' => 'Add-On Request: ' . $addons,
                        'description' => "Clinic: {$tenant->name}\n\nRequested Add-Ons: {$addons}\n\nMessage: " . ($data['message'] ?? 'No additional message'),
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
