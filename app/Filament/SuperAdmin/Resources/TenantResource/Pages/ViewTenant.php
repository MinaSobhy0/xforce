<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Models\AddOn;
use App\Models\SubscriptionPlan;
use Modules\Core\Models\Tenant;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    // Top Action Buttons
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('loginAs')
                ->label('Login As Owner')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('info')
                ->url(fn(): string =>
                    "https://{$this->record->slug}.xlinic.com/admin"
                )
                ->openUrlInNewTab(),

            Actions\Action::make('emailOwner')
                ->label('Send Email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->form([
                    Forms\Components\TextInput::make('subject')->required(),
                    Forms\Components\RichEditor::make('body')->required(),
                ])
                ->action(function (array $data): void {
                    Notification::make()
                        ->title('Email sent')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('suspend')
                ->label('Suspend')
                ->icon('heroicon-o-pause-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => in_array(
                    $this->record->subscription_status,
                    ['active', 'past_due']
                ))
                ->action(function (): void {
                    $this->record->update([
                        'subscription_status' => 'suspended',
                        'status' => 'suspended',
                    ]);
                    Notification::make()->title('Clinic suspended')->danger()->send();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('changePlan')
                    ->label('Change Plan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('subscription_plan_id')
                            ->label('New Plan')
                            ->options(SubscriptionPlan::active()->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->required(),
                        Forms\Components\Toggle::make('prorate')
                            ->label('Prorate current billing period')
                            ->default(true),
                    ])
                    ->action(function (array $data): void {
                        $this->record->update([
                            'subscription_plan_id' => $data['subscription_plan_id'],
                        ]);
                        Notification::make()
                            ->title('Plan changed successfully')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('applyDiscount')
                    ->label('Apply Discount')
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('discount_percentage')
                            ->label('Discount (%)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                        Forms\Components\Select::make('duration')
                            ->label('Duration')
                            ->options([
                                '1' => '1 Month',
                                '3' => '3 Months',
                                '6' => '6 Months',
                                '12' => '12 Months',
                                'forever' => 'Forever',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2),
                    ])
                    ->action(function (array $data): void {
                        // Store discount in tenant meta or settings
                        $settings = $this->record->settings ?? [];
                        $settings['discount'] = [
                            'percentage' => $data['discount_percentage'],
                            'duration' => $data['duration'],
                            'reason' => $data['reason'] ?? null,
                            'applied_at' => now()->toISOString(),
                        ];
                        $this->record->update(['settings' => $settings]);

                        Notification::make()
                            ->title('Discount applied')
                            ->body("{$data['discount_percentage']}% discount applied for {$data['duration']} month(s)")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('addAddOn')
                    ->label('Add Add-On')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('add_on_id')
                            ->label('Add-On')
                            ->options(AddOn::active()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('billing_interval')
                            ->label('Billing Interval')
                            ->options([
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->default('monthly')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $addOn = AddOn::find($data['add_on_id']);
                        $price = $data['billing_interval'] === 'yearly'
                            ? $addOn->yearly_price
                            : $addOn->monthly_price;

                        $this->record->addOns()->attach($data['add_on_id'], [
                            'id' => \Illuminate\Support\Str::uuid(),
                            'activated_at' => now(),
                            'billing_interval' => $data['billing_interval'],
                            'price' => $price,
                            'status' => 'active',
                        ]);

                        Notification::make()
                            ->title('Add-on activated')
                            ->body("{$addOn->name} has been added to this clinic")
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Billing Actions')
                ->icon('heroicon-o-currency-dollar')
                ->color('gray'),

            Actions\Action::make('reactivate')
                ->label('Reactivate')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->subscription_status === 'suspended')
                ->action(function (): void {
                    $this->record->update([
                        'subscription_status' => 'active',
                        'status' => 'active',
                    ]);

                    // Send reactivation email
                    // TODO: Implement email sending

                    Notification::make()
                        ->title('Clinic reactivated')
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make(),

            Actions\Action::make('delete')
                ->label('Delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete Clinic')
                ->modalDescription('Are you sure you want to delete this clinic? This will soft-delete the clinic and all associated data. The clinic can be restored within 90 days.')
                ->modalSubmitActionLabel('Yes, delete clinic')
                ->action(function (): void {
                    $this->record->delete();

                    Notification::make()
                        ->title('Clinic deleted')
                        ->body('The clinic has been soft-deleted and can be restored within 90 days.')
                        ->warning()
                        ->send();

                    $this->redirect(TenantResource::getUrl('index'));
                }),
        ];
    }

    // Tabbed Infolist (Main Content — Screen 3)
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // Header: Clinic Name + Status Badge
            Components\Section::make()
                ->schema([
                    Components\Split::make([
                        Components\Group::make([
                            Components\TextEntry::make('name')
                                ->label('')
                                ->size(Components\TextEntry\TextEntrySize::Large)
                                ->weight(FontWeight::Bold),
                            Components\TextEntry::make('slug')
                                ->label('')
                                ->formatStateUsing(fn(string $state) =>
                                    "{$state}.xlinic.com"
                                )
                                ->color('gray')
                                ->copyable(),
                        ]),
                        Components\Group::make([
                            Components\TextEntry::make('subscription_status')
                                ->label('')
                                ->badge()
                                ->color(fn(?string $state) => match ($state) {
                                    'active' => 'success',
                                    'trial' => 'info',
                                    'past_due' => 'warning',
                                    'suspended' => 'danger',
                                    default => 'gray',
                                }),
                        ])->grow(false),
                    ]),
                ])
                ->columnSpanFull(),

            // TABS
            Components\Tabs::make('Clinic Details')
                ->columnSpanFull()
                ->tabs([

                    // TAB 1: INFO
                    Components\Tabs\Tab::make('Info')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Components\Split::make([
                                // Left: Clinic Details
                                Components\Section::make('Clinic Details')
                                    ->schema([
                                        Components\TextEntry::make('name')
                                            ->label('Name'),
                                        Components\TextEntry::make('slug')
                                            ->label('Subdomain')
                                            ->copyable(),
                                        Components\TextEntry::make('database_name')
                                            ->label('DB Schema')
                                            ->badge()
                                            ->color('gray'),
                                        Components\TextEntry::make('country')
                                            ->label('Country')
                                            ->formatStateUsing(fn(?string $state) => match ($state) {
                                                'EG' => 'Egypt',
                                                'SA' => 'Saudi Arabia',
                                                'AE' => 'UAE',
                                                default => $state ?? '-',
                                            }),
                                        Components\TextEntry::make('timezone'),
                                        Components\TextEntry::make('currency')
                                            ->label('Currency'),
                                        Components\TextEntry::make('domain')
                                            ->label('Custom Domain')
                                            ->default('-')
                                            ->copyable(),
                                        Components\TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime(),
                                    ]),

                                // Right: Owner Details
                                Components\Section::make('Owner')
                                    ->schema([
                                        Components\TextEntry::make('contact_name')
                                            ->label('Name'),
                                        Components\TextEntry::make('contact_email')
                                            ->label('Email')
                                            ->copyable(),
                                        Components\TextEntry::make('contact_phone')
                                            ->label('Phone')
                                            ->copyable(),
                                        Components\TextEntry::make('created_at')
                                            ->label('Joined')
                                            ->since(),
                                    ]),
                            ]),
                        ]),

                    // TAB 2: BILLING
                    Components\Tabs\Tab::make('Billing')
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Components\Section::make('Current Subscription')
                                ->columns(3)
                                ->schema([
                                    Components\TextEntry::make('plan.code')
                                        ->label('Plan')
                                        ->badge()
                                        ->color('info')
                                        ->default(fn(Tenant $record) => $record->subscription_plan ?? 'None')
                                        ->size(Components\TextEntry\TextEntrySize::Large),

                                    Components\TextEntry::make('plan.price_monthly_minor')
                                        ->label('Plan Price')
                                        ->money('EGP', divideBy: 100)
                                        ->default('-'),

                                    Components\TextEntry::make('subscription_status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn(?string $state) => match ($state) {
                                            'active' => 'success',
                                            'trial' => 'info',
                                            'past_due' => 'warning',
                                            'suspended' => 'danger',
                                            default => 'gray',
                                        }),

                                    Components\TextEntry::make('trial_ends_at')
                                        ->label('Trial Ends')
                                        ->date()
                                        ->visible(fn(Tenant $record) => $record->subscription_status === 'trial'),

                                    Components\TextEntry::make('subscription_expires_at')
                                        ->label('Subscription Ends')
                                        ->date(),

                                    Components\TextEntry::make('created_at')
                                        ->label('Customer Since')
                                        ->date(),
                                ]),

                            Components\Section::make('Active Add-Ons')
                                ->schema([
                                    Components\RepeatableEntry::make('activeAddOns')
                                        ->label('')
                                        ->schema([
                                            Components\TextEntry::make('name')
                                                ->label('Add-On'),
                                            Components\TextEntry::make('pivot.price')
                                                ->label('Price')
                                                ->money('EGP'),
                                            Components\TextEntry::make('pivot.billing_interval')
                                                ->label('Billing')
                                                ->badge(),
                                            Components\TextEntry::make('pivot.activated_at')
                                                ->label('Activated')
                                                ->date(),
                                        ])
                                        ->columns(4),
                                ])
                                ->collapsible(),
                        ]),

                    // TAB 3: USAGE
                    Components\Tabs\Tab::make('Usage')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Components\Section::make('Resource Limits')
                                ->columns(3)
                                ->schema([
                                    Components\TextEntry::make('usage.users')
                                        ->label('Users')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->max_users ?? '∞';
                                            return ($state ?? 0) . ' / ' . $limit;
                                        })
                                        ->color(function ($state, Tenant $record) {
                                            $limit = $record->max_users;
                                            if (!$limit) return 'gray';
                                            $pct = ($state ?? 0) / $limit * 100;
                                            if ($pct >= 90) return 'danger';
                                            if ($pct >= 70) return 'warning';
                                            return 'success';
                                        }),

                                    Components\TextEntry::make('usage.patients')
                                        ->label('Patients')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->max_patients ?? '∞';
                                            return ($state ?? 0) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.storage_mb')
                                        ->label('Storage')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $used = round(($state ?? 0) / 1024, 1);
                                            $limit = $record->max_storage_mb;
                                            $limitGb = $limit ? round($limit / 1024, 1) : '∞';
                                            return "{$used} GB / {$limitGb} GB";
                                        }),
                                ]),
                        ]),

                    // TAB 4: MODULES
                    Components\Tabs\Tab::make('Modules')
                        ->icon('heroicon-o-puzzle-piece')
                        ->schema([
                            Components\Section::make('Module Configuration')
                                ->schema([
                                    Components\Actions::make([
                                        Components\Actions\Action::make('forceActivateAll')
                                            ->label('Force Activate All')
                                            ->icon('heroicon-o-check-circle')
                                            ->color('success')
                                            ->requiresConfirmation()
                                            ->modalDescription('This will activate ALL available modules for this tenant, regardless of their plan.')
                                            ->action(function (Tenant $record) {
                                                $allModules = \App\Models\Module::whereRaw('is_active = true')->pluck('code')->toArray();
                                                $record->update(['features' => $allModules]);

                                                Notification::make()
                                                    ->title('All modules activated')
                                                    ->body(count($allModules) . ' modules have been activated for this tenant.')
                                                    ->success()
                                                    ->send();
                                            }),

                                        Components\Actions\Action::make('resetToPlanDefaults')
                                            ->label('Reset to Plan Defaults')
                                            ->icon('heroicon-o-arrow-path')
                                            ->color('warning')
                                            ->requiresConfirmation()
                                            ->modalDescription('This will reset the tenant\'s modules to match their subscription plan\'s included modules.')
                                            ->action(function (Tenant $record) {
                                                $plan = $record->plan;
                                                if ($plan && $plan->included_module_codes) {
                                                    $record->update(['features' => $plan->included_module_codes]);

                                                    Notification::make()
                                                        ->title('Modules reset to plan defaults')
                                                        ->body('Modules have been reset to match the ' . $plan->name . ' plan.')
                                                        ->success()
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->title('No plan assigned')
                                                        ->body('This tenant does not have a subscription plan with defined modules.')
                                                        ->warning()
                                                        ->send();
                                                }
                                            }),
                                    ]),
                                ]),

                            Components\Section::make('Active Modules')
                                ->schema([
                                    Components\TextEntry::make('features')
                                        ->label('')
                                        ->badge()
                                        ->separator(',')
                                        ->color('success'),
                                ]),

                            Components\Section::make('Plan Included Modules')
                                ->schema([
                                    Components\TextEntry::make('plan.included_module_codes')
                                        ->label('')
                                        ->badge()
                                        ->separator(',')
                                        ->color('info')
                                        ->default('-'),
                                ])
                                ->collapsible(),
                        ]),

                    // TAB 5: ACTIVITY
                    Components\Tabs\Tab::make('Activity')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Components\TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->since(),
                        ]),

                    // TAB 6: SUPPORT
                    Components\Tabs\Tab::make('Support')
                        ->icon('heroicon-o-ticket')
                        ->schema([
                            Components\TextEntry::make('contact_email')
                                ->label('Support Contact')
                                ->copyable(),
                        ]),
                ]),
        ]);
    }
}
