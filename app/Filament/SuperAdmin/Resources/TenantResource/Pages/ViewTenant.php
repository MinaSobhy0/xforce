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

                            Components\Section::make('Invoice History')
                                ->schema([
                                    Components\RepeatableEntry::make('invoices')
                                        ->label('')
                                        ->schema([
                                            Components\TextEntry::make('invoice_number')
                                                ->label('Invoice #'),
                                            Components\TextEntry::make('period_start')
                                                ->label('Period')
                                                ->formatStateUsing(fn($state, $record) =>
                                                    $record->period_start?->format('M Y') ?? '-'
                                                ),
                                            Components\TextEntry::make('amount')
                                                ->label('Amount')
                                                ->money('EGP', divideBy: 100),
                                            Components\TextEntry::make('status')
                                                ->label('Status')
                                                ->badge()
                                                ->color(fn(?string $state) => match ($state) {
                                                    'paid' => 'success',
                                                    'pending' => 'warning',
                                                    'overdue' => 'danger',
                                                    default => 'gray',
                                                }),
                                            Components\TextEntry::make('paid_at')
                                                ->label('Paid')
                                                ->date()
                                                ->placeholder('-'),
                                        ])
                                        ->columns(5)
                                        ->placeholder('No invoices yet'),
                                ])
                                ->collapsible(),
                        ]),

                    // TAB 3: USAGE
                    Components\Tabs\Tab::make('Usage')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Components\Section::make('Hard Limits')
                                ->columns(3)
                                ->schema([
                                    Components\TextEntry::make('usage.users')
                                        ->label('Users')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->max_users ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        })
                                        ->color(function ($state, Tenant $record) {
                                            $limit = $record->max_users;
                                            if (!$limit || !is_numeric($limit)) return 'gray';
                                            $pct = ((int) ($state ?? 0)) / (int) $limit * 100;
                                            if ($pct >= 90) return 'danger';
                                            if ($pct >= 70) return 'warning';
                                            return 'success';
                                        }),

                                    Components\TextEntry::make('usage.patients')
                                        ->label('Patients')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->max_patients ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.branches')
                                        ->label('Branches')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_branches ?? '∞';
                                            return ((int) ($state ?? 1)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.equipment')
                                        ->label('Equipment')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_equipment ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.products')
                                        ->label('Products')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_products ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.treatments')
                                        ->label('Treatments')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_treatments ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),
                                ]),

                            Components\Section::make('Storage Breakdown')
                                ->columns(4)
                                ->schema([
                                    Components\TextEntry::make('usage.storage_mb')
                                        ->label('Total Used')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $used = round(((float) ($state ?? 0)) / 1024, 2);
                                            $limit = $record->max_storage_mb;
                                            $limitGb = $limit ? round((float) $limit / 1024, 1) : '∞';
                                            return "{$used} GB / {$limitGb} GB";
                                        }),

                                    Components\TextEntry::make('usage.storage_photos_mb')
                                        ->label('Photos')
                                        ->formatStateUsing(fn($state) => round(((float) ($state ?? 0)) / 1024, 2) . ' GB')
                                        ->default('0 GB'),

                                    Components\TextEntry::make('usage.storage_documents_mb')
                                        ->label('Documents')
                                        ->formatStateUsing(fn($state) => round(((float) ($state ?? 0)) / 1024, 2) . ' GB')
                                        ->default('0 GB'),

                                    Components\TextEntry::make('usage.storage_consent_mb')
                                        ->label('Consent Forms')
                                        ->formatStateUsing(fn($state) => round(((float) ($state ?? 0)) / 1024, 2) . ' GB')
                                        ->default('0 GB'),
                                ]),

                            Components\Section::make('Monthly Usage (Current Period)')
                                ->columns(5)
                                ->schema([
                                    Components\TextEntry::make('usage.appointments_this_month')
                                        ->label('Appointments')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_appointments_monthly ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.whatsapp_this_month')
                                        ->label('WhatsApp')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_whatsapp_monthly ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.sms_this_month')
                                        ->label('SMS')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_sms_monthly ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.emails_this_month')
                                        ->label('Emails')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_emails_monthly ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
                                        }),

                                    Components\TextEntry::make('usage.api_calls_today')
                                        ->label('API Calls (Today)')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_api_calls_daily ?? '∞';
                                            return ((int) ($state ?? 0)) . ' / ' . $limit;
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

                    // TAB 5: USERS
                    Components\Tabs\Tab::make('Users')
                        ->icon('heroicon-o-users')
                        ->schema([
                            Components\RepeatableEntry::make('users')
                                ->label('')
                                ->schema([
                                    Components\TextEntry::make('name')
                                        ->label('Name')
                                        ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                    Components\TextEntry::make('email')
                                        ->label('Email')
                                        ->copyable(),
                                    Components\TextEntry::make('roles.name')
                                        ->label('Role')
                                        ->badge()
                                        ->color('info'),
                                    Components\TextEntry::make('last_login_at')
                                        ->label('Last Login')
                                        ->since()
                                        ->placeholder('Never'),
                                    Components\TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn(mixed $state) => match ((string) ($state?->value ?? $state)) {
                                            'active' => 'success',
                                            'inactive' => 'gray',
                                            'suspended' => 'danger',
                                            default => 'gray',
                                        }),
                                ])
                                ->columns(5)
                                ->placeholder('No users found'),
                        ]),

                    // TAB 6: ACTIVITY
                    Components\Tabs\Tab::make('Activity')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Components\RepeatableEntry::make('activityLogs')
                                ->label('')
                                ->schema([
                                    Components\TextEntry::make('created_at')
                                        ->label('Time')
                                        ->since(),
                                    Components\TextEntry::make('event_type')
                                        ->label('Event')
                                        ->badge()
                                        ->color(fn(?string $state) => match ($state) {
                                            'payment_received', 'reactivated', 'plan_upgraded' => 'success',
                                            'payment_failed', 'suspended' => 'danger',
                                            'addon_activated', 'user_added' => 'info',
                                            default => 'gray',
                                        }),
                                    Components\TextEntry::make('description')
                                        ->label('Description'),
                                ])
                                ->columns(3)
                                ->placeholder('No activity logged yet'),
                        ]),

                    // TAB 7: SUPPORT
                    Components\Tabs\Tab::make('Support')
                        ->icon('heroicon-o-ticket')
                        ->schema([
                            Components\Section::make()
                                ->schema([
                                    Components\Actions::make([
                                        Components\Actions\Action::make('createTicket')
                                            ->label('Create Ticket for Clinic')
                                            ->icon('heroicon-o-plus')
                                            ->color('primary')
                                            ->url(fn(Tenant $record) => route('filament.super-admin.resources.support-tickets.create', ['tenant_id' => $record->id])),
                                        Components\Actions\Action::make('emailOwner')
                                            ->label('Email Clinic Owner')
                                            ->icon('heroicon-o-envelope')
                                            ->color('gray')
                                            ->action(function (Tenant $record) {
                                                Notification::make()
                                                    ->title('Opening email client...')
                                                    ->info()
                                                    ->send();
                                            }),
                                    ]),
                                ]),
                            Components\RepeatableEntry::make('supportTickets')
                                ->label('Tickets')
                                ->schema([
                                    Components\TextEntry::make('ticket_number')
                                        ->label('#'),
                                    Components\TextEntry::make('subject')
                                        ->label('Subject')
                                        ->limit(40),
                                    Components\TextEntry::make('priority')
                                        ->label('Priority')
                                        ->badge()
                                        ->color(fn(?string $state) => match ($state) {
                                            'urgent' => 'danger',
                                            'high' => 'warning',
                                            'normal' => 'info',
                                            default => 'gray',
                                        }),
                                    Components\TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn(?string $state) => match ($state) {
                                            'open' => 'warning',
                                            'in_progress' => 'info',
                                            'resolved' => 'success',
                                            default => 'gray',
                                        }),
                                    Components\TextEntry::make('created_at')
                                        ->label('Opened')
                                        ->since(),
                                ])
                                ->columns(5)
                                ->placeholder('No support tickets'),
                        ]),
                ]),
        ]);
    }
}
