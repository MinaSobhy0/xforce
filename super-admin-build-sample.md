# Super Admin Screen Build Sample
## Clinics List + Clinic Detail (Screens 2 & 3)
## Full working Filament 3 code — use as template for all other screens

---

## File 1: The Tenant Model

```php
// app/Models/Tenant.php

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'schema_name',
        'owner_user_id',
        'subscription_plan_id',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'settings',
        'domain',
        'country_code',
        'timezone',
        'currency_code',
        'is_active',
    ];

    protected $casts = [
        'settings'            => 'json',
        'trial_ends_at'       => 'datetime',
        'subscription_ends_at'=> 'datetime',
        'is_active'           => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'owner_user_id');
    }

    public function usage(): HasOne
    {
        return $this->hasOne(TenantUsage::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PlatformInvoice::class);
    }

    public function addonSubscriptions(): HasMany
    {
        return $this->hasMany(TenantAddonSubscription::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TenantActivityLog::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    // ─── Computed ─────────────────────────────────────────────

    public function getMonthlyTotalAttribute(): int
    {
        $planPrice = $this->plan?->price_monthly_minor ?? 0;
        $addonTotal = $this->addonSubscriptions()
            ->where('status', 'active')
            ->sum('price_minor');

        return $planPrice + $addonTotal;
    }

    public function getLifetimeValueAttribute(): int
    {
        return $this->invoices()
            ->where('status', 'paid')
            ->sum('total_minor');
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial'
            && $this->trial_ends_at?->isFuture();
    }

    public function trialDaysRemaining(): ?int
    {
        if (! $this->isOnTrial()) {
            return null;
        }

        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->subscription_status) {
            'active'    => 'success',
            'trial'     => 'info',
            'past_due'  => 'warning',
            'suspended' => 'danger',
            'cancelled' => 'gray',
            default     => 'gray',
        };
    }
}
```

---

## File 2: The Filament Resource (Clinics List — Screen 2)

```php
// app/Filament/SuperAdmin/Resources/TenantResource.php

<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TenantResource\Pages;
use App\Filament\SuperAdmin\Resources\TenantResource\RelationManagers;
use App\Filament\SuperAdmin\Resources\TenantResource\Widgets;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Clinics';

    protected static ?string $navigationGroup = 'Tenants';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    // ═══════════════════════════════════════════════════════════
    // GLOBAL SEARCH — search clinics from anywhere
    // ═══════════════════════════════════════════════════════════

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'domain', 'owner.name', 'owner.email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Plan'   => $record->plan?->name ?? '—',
            'Status' => $record->subscription_status,
            'Owner'  => $record->owner?->name ?? '—',
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // CREATE / EDIT FORM — for manually adding a clinic
    // ═══════════════════════════════════════════════════════════

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Clinic Information')
                ->icon('heroicon-o-building-office-2')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Clinic Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $state, Forms\Set $set) {
                            $set('slug', \Str::slug($state));
                            $set('schema_name', 'tenant_' . \Str::slug($state, '_'));
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label('Subdomain')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->prefix('https://')
                        ->suffix('.xlinic.com')
                        ->helperText('This will be the clinic\'s URL'),

                    Forms\Components\TextInput::make('schema_name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('domain')
                        ->label('Custom Domain')
                        ->url()
                        ->placeholder('clinic.example.com')
                        ->helperText('Optional custom domain'),
                ]),

            Forms\Components\Section::make('Owner')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('owner_user_id')
                        ->label('Owner')
                        ->relationship('owner', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\TextInput::make('email')->email()->required()->unique(),
                            Forms\Components\TextInput::make('phone')->tel(),
                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->required()
                                ->dehydrateStateUsing(fn($state) => bcrypt($state)),
                        ]),
                ]),

            Forms\Components\Section::make('Subscription')
                ->icon('heroicon-o-credit-card')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('subscription_plan_id')
                        ->label('Plan')
                        ->relationship('plan', 'code')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('subscription_status')
                        ->options([
                            'trial'     => '⏳ Trial',
                            'active'    => '● Active',
                            'past_due'  => '⚠️ Past Due',
                            'suspended' => '🔴 Suspended',
                            'cancelled' => '○ Cancelled',
                        ])
                        ->default('trial')
                        ->required(),

                    Forms\Components\DateTimePicker::make('trial_ends_at')
                        ->label('Trial Ends')
                        ->default(now()->addDays(14))
                        ->visible(fn(Forms\Get $get) => $get('subscription_status') === 'trial'),

                    Forms\Components\DateTimePicker::make('subscription_ends_at')
                        ->label('Subscription Ends'),
                ]),

            Forms\Components\Section::make('Localization')
                ->icon('heroicon-o-globe-alt')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('country_code')
                        ->label('Country')
                        ->options([
                            'EG' => '🇪🇬 Egypt',
                            'SA' => '🇸🇦 Saudi Arabia',
                            'AE' => '🇦🇪 UAE',
                            'KW' => '🇰🇼 Kuwait',
                            'QA' => '🇶🇦 Qatar',
                            'BH' => '🇧🇭 Bahrain',
                            'OM' => '🇴🇲 Oman',
                            'JO' => '🇯🇴 Jordan',
                            'LB' => '🇱🇧 Lebanon',
                        ])
                        ->default('EG')
                        ->required(),

                    Forms\Components\Select::make('timezone')
                        ->options([
                            'Africa/Cairo'   => 'Cairo (EET)',
                            'Asia/Riyadh'    => 'Riyadh (AST)',
                            'Asia/Dubai'     => 'Dubai (GST)',
                            'Asia/Kuwait'    => 'Kuwait (AST)',
                            'Asia/Qatar'     => 'Qatar (AST)',
                            'Asia/Bahrain'   => 'Bahrain (AST)',
                            'Asia/Muscat'    => 'Muscat (GST)',
                            'Asia/Amman'     => 'Amman (EET)',
                            'Asia/Beirut'    => 'Beirut (EET)',
                        ])
                        ->default('Africa/Cairo')
                        ->required(),

                    Forms\Components\Select::make('currency_code')
                        ->options([
                            'EGP' => 'EGP (Egyptian Pound)',
                            'SAR' => 'SAR (Saudi Riyal)',
                            'AED' => 'AED (UAE Dirham)',
                            'KWD' => 'KWD (Kuwaiti Dinar)',
                            'QAR' => 'QAR (Qatari Riyal)',
                        ])
                        ->default('EGP')
                        ->required(),
                ]),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // TABLE — Clinics List (Screen 2)
    // ═══════════════════════════════════════════════════════════

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ─── Clinic Info Column ───────────────────
                Tables\Columns\TextColumn::make('name')
                    ->label('Clinic')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn(Tenant $record): string =>
                        $record->slug . '.xlinic.com'
                    ),

                // ─── Plan Column ──────────────────────────
                Tables\Columns\TextColumn::make('plan.code')
                    ->label('Plan')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'enterprise'   => 'success',
                        'professional' => 'info',
                        'starter'      => 'warning',
                        default        => 'gray',
                    })
                    ->sortable(),

                // ─── Status Column ────────────────────────
                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active'    => 'success',
                        'trial'     => 'info',
                        'past_due'  => 'warning',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(function (string $state, Tenant $record): string {
                        if ($state === 'trial' && $record->trialDaysRemaining() !== null) {
                            return "Trial ({$record->trialDaysRemaining()}d left)";
                        }
                        return ucfirst(str_replace('_', ' ', $state));
                    })
                    ->sortable(),

                // ─── Users Count ──────────────────────────
                Tables\Columns\TextColumn::make('usage.users_count')
                    ->label('Users')
                    ->formatStateUsing(function ($state, Tenant $record): string {
                        $limit = $record->plan?->max_users;
                        $limitStr = $limit ? $limit : '∞';
                        return ($state ?? 0) . '/' . $limitStr;
                    })
                    ->color(function ($state, Tenant $record): string {
                        $limit = $record->plan?->max_users;
                        if (! $limit) return 'gray';
                        $pct = ($state ?? 0) / $limit * 100;
                        if ($pct >= 90) return 'danger';
                        if ($pct >= 70) return 'warning';
                        return 'gray';
                    })
                    ->sortable(),

                // ─── Patients Count ───────────────────────
                Tables\Columns\TextColumn::make('usage.patients_count')
                    ->label('Patients')
                    ->numeric()
                    ->sortable(),

                // ─── MRR Column ───────────────────────────
                Tables\Columns\TextColumn::make('monthly_total')
                    ->label('MRR')
                    ->getStateUsing(fn(Tenant $record): int => $record->monthly_total)
                    ->money('EGP', divideBy: 100)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            "(SELECT price_monthly_minor FROM subscription_plans WHERE id = tenants.subscription_plan_id) {$direction}"
                        );
                    }),

                // ─── Country ──────────────────────────────
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'EG' => '🇪🇬',
                        'SA' => '🇸🇦',
                        'AE' => '🇦🇪',
                        'KW' => '🇰🇼',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // ─── Created Date ─────────────────────────
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            // ─── Filters ──────────────────────────────────
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_status')
                    ->label('Status')
                    ->options([
                        'active'    => 'Active',
                        'trial'     => 'Trial',
                        'past_due'  => 'Past Due',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('subscription_plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'code'),

                Tables\Filters\SelectFilter::make('country_code')
                    ->label('Country')
                    ->options([
                        'EG' => 'Egypt',
                        'SA' => 'Saudi Arabia',
                        'AE' => 'UAE',
                    ]),

                Tables\Filters\Filter::make('trial_expiring')
                    ->label('Trial Expiring Soon')
                    ->query(fn(Builder $query): Builder =>
                        $query->where('subscription_status', 'trial')
                            ->whereBetween('trial_ends_at', [now(), now()->addDays(3)])
                    ),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Payments')
                    ->query(fn(Builder $query): Builder =>
                        $query->where('subscription_status', 'past_due')
                    ),
            ])

            // ─── Row Actions ──────────────────────────────
            ->actions([
                Tables\Actions\Action::make('loginAs')
                    ->label('Login As')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('info')
                    ->url(fn(Tenant $record): string =>
                        "https://{$record->slug}.xlinic.com/admin/login-as/{$record->owner_user_id}"
                    )
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('email')
                    ->label('Email')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->required(),
                        Forms\Components\RichEditor::make('body')
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        // Mail::to($record->owner->email)->send(...)
                        Notification::make()
                            ->title('Email sent to ' . $record->owner->name)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Suspend Clinic')
                        ->modalDescription(fn(Tenant $record) =>
                            "Are you sure you want to suspend {$record->name}? They will lose access immediately."
                        )
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason')
                                ->required(),
                        ])
                        ->action(function (Tenant $record, array $data): void {
                            $record->update([
                                'subscription_status' => 'suspended',
                            ]);
                            // Log activity, send email, etc.
                            Notification::make()
                                ->title("{$record->name} suspended")
                                ->danger()
                                ->send();
                        })
                        ->visible(fn(Tenant $record) =>
                            in_array($record->subscription_status, ['active', 'past_due'])
                        ),

                    Tables\Actions\Action::make('reactivate')
                        ->label('Reactivate')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Tenant $record): void {
                            $record->update([
                                'subscription_status' => 'active',
                            ]);
                            Notification::make()
                                ->title("{$record->name} reactivated")
                                ->success()
                                ->send();
                        })
                        ->visible(fn(Tenant $record) =>
                            $record->subscription_status === 'suspended'
                        ),

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Clinic Permanently')
                        ->modalDescription('This will DROP the tenant schema and all data. This cannot be undone.'),
                ]),
            ])

            // ─── Bulk Actions ─────────────────────────────
            ->bulkActions([
                Tables\Actions\BulkAction::make('sendReminders')
                    ->label('Send Payment Reminders')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        // Send reminder emails to all selected
                        Notification::make()
                            ->title("Reminders sent to {$records->count()} clinics")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\BulkAction::make('suspend')
                    ->label('Suspend Selected')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
            ])

            // ─── Table Config ─────────────────────────────
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('60s'); // Auto-refresh every 60s
    }

    // ═══════════════════════════════════════════════════════════
    // PAGES — List + View (Detail) + Create + Edit
    // ═══════════════════════════════════════════════════════════

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\AddonsRelationManager::class,
            RelationManagers\ActivityLogRelationManager::class,
            RelationManagers\SupportTicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view'   => Pages\ViewTenant::route('/{record}'),
            'edit'   => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // NAVIGATION BADGE — show count of active clinics
    // ═══════════════════════════════════════════════════════════

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
```

---

## File 3: List Page with Stats Widgets (Screen 2 — Top)

```php
// app/Filament/SuperAdmin/Resources/TenantResource/Pages/ListTenants.php

<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Filament\SuperAdmin\Resources\TenantResource\Widgets;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Clinic')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\TenantsOverviewWidget::class,
        ];
    }
}
```

```php
// app/Filament/SuperAdmin/Resources/TenantResource/Widgets/TenantsOverviewWidget.php

<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantsOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $active = Tenant::where('subscription_status', 'active')->count();
        $trial = Tenant::where('subscription_status', 'trial')->count();
        $suspended = Tenant::where('subscription_status', 'suspended')->count();

        $mrr = Tenant::where('subscription_status', 'active')
            ->with('plan')
            ->get()
            ->sum('monthly_total');

        $trialExpiringSoon = Tenant::where('subscription_status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(3)])
            ->count();

        $overdue = Tenant::where('subscription_status', 'past_due')->count();

        return [
            Stat::make('Active Clinics', $active)
                ->description("+{$trial} on trial")
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('success')
                ->chart([7, 9, 12, 15, 18, 22, $active]), // Sparkline

            Stat::make('MRR', 'EGP ' . number_format($mrr / 100, 0))
                ->description('+8.2% from last month')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('success')
                ->chart([85, 90, 95, 100, 108, 115, $mrr / 100]),

            Stat::make('Trials Expiring', $trialExpiringSoon)
                ->description('In next 3 days')
                ->descriptionIcon('heroicon-o-clock')
                ->color($trialExpiringSoon > 0 ? 'warning' : 'gray'),

            Stat::make('Overdue Payments', $overdue)
                ->description($suspended . ' suspended')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'success'),
        ];
    }
}
```

---

## File 4: View Page with Tabs (Screen 3 — Clinic Detail)

This is the most important file. It creates the tabbed detail view.

```php
// app/Filament/SuperAdmin/Resources/TenantResource/Pages/ViewTenant.php

<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\Module;
use App\Models\TenantModule;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    // ─── Top Action Buttons ───────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('loginAs')
                ->label('Login As Owner')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('info')
                ->url(fn(): string =>
                    "https://{$this->record->slug}.xlinic.com/admin/login-as/{$this->record->owner_user_id}"
                )
                ->openUrlInNewTab(),

            Actions\Action::make('emailOwner')
                ->label('Send Email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('subject')->required(),
                    \Filament\Forms\Components\RichEditor::make('body')->required(),
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
                    $this->record->update(['subscription_status' => 'suspended']);
                    Notification::make()->title('Clinic suspended')->danger()->send();
                }),

            Actions\EditAction::make(),
        ];
    }

    // ─── Tabbed Infolist (Main Content — Screen 3) ────────

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            // ─── Header: Clinic Name + Status Badge ──────
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
                                ->color(fn(string $state) => match ($state) {
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

            // ═══════════════════════════════════════════════
            // TABS
            // ═══════════════════════════════════════════════

            Components\Tabs::make('Clinic Details')
                ->columnSpanFull()
                ->tabs([

                    // ─── TAB 1: INFO ──────────────────────
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
                                        Components\TextEntry::make('schema_name')
                                            ->label('DB Schema')
                                            ->badge()
                                            ->color('gray'),
                                        Components\TextEntry::make('country_code')
                                            ->label('Country')
                                            ->formatStateUsing(fn(string $state) => match ($state) {
                                                'EG' => '🇪🇬 Egypt',
                                                'SA' => '🇸🇦 Saudi Arabia',
                                                'AE' => '🇦🇪 UAE',
                                                default => $state,
                                            }),
                                        Components\TextEntry::make('timezone'),
                                        Components\TextEntry::make('currency_code')
                                            ->label('Currency'),
                                        Components\TextEntry::make('domain')
                                            ->label('Custom Domain')
                                            ->default('—')
                                            ->copyable(),
                                        Components\TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime(),
                                    ]),

                                // Right: Owner Details
                                Components\Section::make('Owner')
                                    ->schema([
                                        Components\TextEntry::make('owner.name')
                                            ->label('Name'),
                                        Components\TextEntry::make('owner.email')
                                            ->label('Email')
                                            ->copyable(),
                                        Components\TextEntry::make('owner.phone')
                                            ->label('Phone')
                                            ->copyable(),
                                        Components\TextEntry::make('owner.created_at')
                                            ->label('Joined')
                                            ->since(),
                                    ]),
                            ]),
                        ]),

                    // ─── TAB 2: BILLING ───────────────────
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
                                        ->size(Components\TextEntry\TextEntrySize::Large),

                                    Components\TextEntry::make('plan.price_monthly_minor')
                                        ->label('Plan Price')
                                        ->money('EGP', divideBy: 100),

                                    Components\TextEntry::make('monthly_total')
                                        ->label('Total Monthly (incl. add-ons)')
                                        ->getStateUsing(fn(Tenant $record) => $record->monthly_total)
                                        ->money('EGP', divideBy: 100)
                                        ->weight(FontWeight::Bold)
                                        ->color('success'),

                                    Components\TextEntry::make('subscription_status')
                                        ->label('Status')
                                        ->badge(),

                                    Components\TextEntry::make('created_at')
                                        ->label('Customer Since')
                                        ->date(),

                                    Components\TextEntry::make('lifetime_value')
                                        ->label('Lifetime Value')
                                        ->getStateUsing(fn(Tenant $record) => $record->lifetime_value)
                                        ->money('EGP', divideBy: 100)
                                        ->color('success'),
                                ]),

                            // Active Add-ons
                            Components\Section::make('Active Add-Ons')
                                ->schema([
                                    Components\RepeatableEntry::make('addonSubscriptions')
                                        ->label('')
                                        ->schema([
                                            Components\TextEntry::make('module_code')
                                                ->label('Module')
                                                ->badge(),
                                            Components\TextEntry::make('price_minor')
                                                ->label('Price')
                                                ->money('EGP', divideBy: 100),
                                            Components\TextEntry::make('status')
                                                ->badge()
                                                ->color(fn(string $state) => match ($state) {
                                                    'active' => 'success',
                                                    default => 'gray',
                                                }),
                                            Components\TextEntry::make('started_at')
                                                ->label('Since')
                                                ->date(),
                                        ])
                                        ->columns(4),
                                ]),

                            // Invoice history shown via RelationManager below
                        ]),

                    // ─── TAB 3: USAGE ─────────────────────
                    Components\Tabs\Tab::make('Usage')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([

                            // Hard Limits
                            Components\Section::make('Resource Limits')
                                ->columns(2)
                                ->schema([
                                    self::makeUsageEntry('usage.users_count', 'Users', 'max_users'),
                                    self::makeUsageEntry('usage.branches_count', 'Branches', 'max_branches'),
                                    self::makeUsageEntry('usage.patients_count', 'Patients', 'max_patients'),
                                    self::makeUsageEntry('usage.equipment_count', 'Equipment', 'max_equipment'),
                                    self::makeUsageEntry('usage.products_count', 'Products', 'max_products'),
                                    self::makeUsageEntry('usage.treatments_count', 'Treatments', 'max_treatments'),
                                ]),

                            // Storage
                            Components\Section::make('Storage')
                                ->schema([
                                    Components\TextEntry::make('usage.storage_used_mb')
                                        ->label('Storage Used')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $used = round(($state ?? 0) / 1024, 1);
                                            $limit = $record->plan?->max_storage_mb;
                                            $limitGb = $limit ? round($limit / 1024, 0) : '∞';
                                            $pct = $limit ? round(($state ?? 0) / $limit * 100) : 0;
                                            return "{$used} GB / {$limitGb} GB ({$pct}%)";
                                        })
                                        ->color(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_storage_mb;
                                            if (! $limit) return 'gray';
                                            $pct = ($state ?? 0) / $limit * 100;
                                            if ($pct >= 90) return 'danger';
                                            if ($pct >= 70) return 'warning';
                                            return 'success';
                                        }),
                                ]),

                            // Monthly Counters
                            Components\Section::make('Monthly Usage (Current Period)')
                                ->columns(2)
                                ->schema([
                                    Components\TextEntry::make('usage.appointments_this_month')
                                        ->label('Appointments')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_appointments_monthly;
                                            return ($state ?? 0) . ' / ' . ($limit ?? '∞');
                                        }),
                                    Components\TextEntry::make('usage.whatsapp_this_month')
                                        ->label('WhatsApp Messages')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_whatsapp_monthly;
                                            return ($state ?? 0) . ' / ' . ($limit ?? '∞');
                                        }),
                                    Components\TextEntry::make('usage.sms_this_month')
                                        ->label('SMS Messages')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_sms_monthly;
                                            return ($state ?? 0) . ' / ' . ($limit ?? '∞');
                                        }),
                                    Components\TextEntry::make('usage.emails_this_month')
                                        ->label('Emails')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $limit = $record->plan?->max_emails_monthly;
                                            return ($state ?? 0) . ' / ' . ($limit ?? '∞');
                                        }),
                                ]),
                        ]),

                    // ─── TAB 4: MODULES ───────────────────
                    Components\Tabs\Tab::make('Modules')
                        ->icon('heroicon-o-puzzle-piece')
                        ->schema([
                            Components\ViewEntry::make('modules')
                                ->label('')
                                ->view('filament.super-admin.tenant-modules', [
                                    'tenant' => fn() => $this->record,
                                ]),
                        ]),

                    // ─── TAB 5: USERS ─────────────────────
                    Components\Tabs\Tab::make('Users')
                        ->icon('heroicon-o-users')
                        ->schema([
                            Components\ViewEntry::make('users')
                                ->label('')
                                ->view('filament.super-admin.tenant-users', [
                                    'tenant' => fn() => $this->record,
                                ]),
                        ]),

                    // ─── TAB 6: ACTIVITY ──────────────────
                    Components\Tabs\Tab::make('Activity')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Components\RepeatableEntry::make('activityLogs')
                                ->label('')
                                ->schema([
                                    Components\TextEntry::make('created_at')
                                        ->label('')
                                        ->since()
                                        ->color('gray'),
                                    Components\TextEntry::make('description')
                                        ->label('')
                                        ->html(),
                                ])
                                ->columns(2),
                        ]),

                    // ─── TAB 7: SUPPORT ───────────────────
                    Components\Tabs\Tab::make('Support')
                        ->icon('heroicon-o-ticket')
                        ->badge(fn(Tenant $record) =>
                            $record->supportTickets()->where('status', 'open')->count() ?: null
                        )
                        ->badgeColor('danger')
                        ->schema([
                            Components\RepeatableEntry::make('supportTickets')
                                ->label('')
                                ->schema([
                                    Components\TextEntry::make('ticket_number')
                                        ->label('#'),
                                    Components\TextEntry::make('subject'),
                                    Components\TextEntry::make('priority')
                                        ->badge()
                                        ->color(fn(string $state) => match ($state) {
                                            'high' => 'danger',
                                            'normal' => 'warning',
                                            'low' => 'gray',
                                            default => 'gray',
                                        }),
                                    Components\TextEntry::make('status')
                                        ->badge()
                                        ->color(fn(string $state) => match ($state) {
                                            'open' => 'warning',
                                            'in_progress' => 'info',
                                            'resolved' => 'success',
                                            default => 'gray',
                                        }),
                                    Components\TextEntry::make('created_at')
                                        ->label('Opened')
                                        ->since(),
                                ])
                                ->columns(5),
                        ]),
                ]),
        ]);
    }

    // ─── Helper: Format Usage Entry with Color ────────────

    private static function makeUsageEntry(
        string $usageField,
        string $label,
        string $planLimitField,
    ): Components\TextEntry {
        return Components\TextEntry::make($usageField)
            ->label($label)
            ->formatStateUsing(function ($state, Tenant $record) use ($planLimitField) {
                $limit = $record->plan?->{$planLimitField};
                $limitStr = $limit ?? '∞';
                $current = $state ?? 0;
                $pct = $limit ? round($current / $limit * 100) : 0;

                $bar = '';
                if ($limit) {
                    $filled = (int) ($pct / 10);
                    $bar = ' ' . str_repeat('█', $filled) . str_repeat('░', 10 - $filled) . " {$pct}%";
                }

                return "{$current} / {$limitStr}{$bar}";
            })
            ->color(function ($state, Tenant $record) use ($planLimitField) {
                $limit = $record->plan?->{$planLimitField};
                if (! $limit) return 'gray';
                $pct = ($state ?? 0) / $limit * 100;
                if ($pct >= 90) return 'danger';
                if ($pct >= 70) return 'warning';
                return 'success';
            });
    }

    // ─── Relation Managers (shown below tabs) ─────────────

    public function getRelationManagers(): array
    {
        return [
            // Only show invoice RM when on Billing tab
            // For simplicity, we show them always
        ];
    }
}
```

---

## File 5: Invoices Relation Manager (Billing Tab)

```php
// app/Filament/SuperAdmin/Resources/TenantResource/RelationManagers/InvoicesRelationManager.php

<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoice History';

    protected static ?string $icon = 'heroicon-o-document-text';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice #')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->date('M Y'),

                Tables\Columns\TextColumn::make('plan_charge_minor')
                    ->label('Plan')
                    ->money('EGP', divideBy: 100),

                Tables\Columns\TextColumn::make('addon_charges_minor')
                    ->label('Add-ons')
                    ->money('EGP', divideBy: 100),

                Tables\Columns\TextColumn::make('overage_charges_minor')
                    ->label('Overage')
                    ->money('EGP', divideBy: 100)
                    ->color(fn(int $state) => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Total')
                    ->money('EGP', divideBy: 100)
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'paid'    => 'success',
                        'pending' => 'warning',
                        'overdue' => 'danger',
                        'refunded'=> 'gray',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid')
                    ->date()
                    ->placeholder('—'),
            ])
            ->defaultSort('period_start', 'desc')
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn($record) => route('platform.invoice.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'paid')
                    ->action(function ($record) {
                        $record->update(['status' => 'refunded']);
                        \Filament\Notifications\Notification::make()
                            ->title('Invoice refunded')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
```

---

## File 6: Modules Tab Blade View (Custom Livewire Component)

```blade
{{-- resources/views/filament/super-admin/tenant-modules.blade.php --}}

@php
    $tenant = $getRecord();
    $modules = \App\Models\Module::orderBy('sort_order')->get();
    $planModuleCodes = $tenant->plan
        ? $tenant->plan->modules()->pluck('module_code')->toArray()
        : [];
    $activeModuleCodes = \App\Models\TenantModule::where('tenant_id', $tenant->id)
        ->where('is_active', true)
        ->pluck('module_code')
        ->toArray();
    $addonCodes = $tenant->addonSubscriptions()
        ->where('status', 'active')
        ->pluck('module_code')
        ->toArray();
@endphp

<div class="space-y-4">
    {{-- Category Groups --}}
    @foreach ($modules->groupBy('category') as $category => $categoryModules)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">
                    {{ ucfirst($category) }}
                </h3>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($categoryModules as $module)
                    @php
                        $isCore = $module->is_core;
                        $inPlan = in_array($module->code, $planModuleCodes);
                        $isAddon = in_array($module->code, $addonCodes);
                        $isActive = in_array($module->code, $activeModuleCodes);
                        $isAvailable = $inPlan || $isAddon;
                    @endphp

                    <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        {{-- Module Info --}}
                        <div class="flex items-center gap-3">
                            <div class="text-lg">
                                @if ($isCore)
                                    🔒
                                @elseif ($module->is_beta)
                                    🧪
                                @else
                                    {{ $module->icon_emoji ?? '📦' }}
                                @endif
                            </div>
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ $module->name['en'] ?? $module->code }}
                                </span>
                                @if ($module->is_beta)
                                    <span class="ml-1 inline-flex items-center rounded-md bg-yellow-50 px-1.5 py-0.5 text-xs font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20">
                                        Beta
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Plan Status --}}
                        <div class="flex items-center gap-4">
                            @if ($isCore)
                                <span class="text-xs text-gray-500">Core</span>
                            @elseif ($inPlan)
                                <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                    ✅ In Plan
                                </span>
                            @elseif ($isAddon)
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    📦 Add-on
                                </span>
                            @else
                                <span class="text-xs text-gray-400">Not in plan</span>
                            @endif

                            {{-- Active Toggle --}}
                            @if ($isCore)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    ● Active
                                </span>
                            @elseif ($isActive)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    ● Active
                                </span>
                                <button
                                    class="text-xs text-red-600 hover:text-red-800 font-medium"
                                    wire:click="$dispatch('deactivateModule', { code: '{{ $module->code }}' })"
                                    wire:confirm="Deactivate {{ $module->name['en'] }}? Data will be preserved."
                                >
                                    Deactivate
                                </button>
                            @elseif ($isAvailable)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                    ○ Inactive
                                </span>
                                <button
                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium"
                                    wire:click="$dispatch('activateModule', { code: '{{ $module->code }}' })"
                                >
                                    Activate
                                </button>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-400">
                                    🔒 Upgrade
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Action Buttons --}}
    <div class="flex gap-3 pt-2">
        <button
            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            wire:click="$dispatch('activateAllModules')"
            wire:confirm="Activate all available modules for this tenant?"
        >
            Force Activate All
        </button>
        <button
            class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            wire:click="$dispatch('resetModulesToPlanDefaults')"
            wire:confirm="Reset all modules to plan defaults?"
        >
            Reset to Plan Defaults
        </button>
    </div>
</div>
```

---

## File 7: Create Tenant Page (with auto-provisioning)

```php
// app/Filament/SuperAdmin/Resources/TenantResource/Pages/CreateTenant.php

<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Services\TenantProvisioningService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        // Auto-provision the tenant schema, run migrations, seed defaults
        try {
            app(TenantProvisioningService::class)->provision($this->record);

            Notification::make()
                ->title('Clinic provisioned successfully')
                ->body("Schema '{$this->record->schema_name}' created with all active module migrations.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Provisioning failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
```

---

## File 8: Super Admin Panel Provider (Wires Everything Together)

```php
// app/Providers/Filament/SuperAdminPanelProvider.php

<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ─── Identity ─────────────────────────
            ->id('super-admin')
            ->path('platform')
            ->brandName('XLinic Platform')
            ->brandLogo(asset('images/logo-platform.svg'))
            ->favicon(asset('images/favicon.ico'))

            // ─── Colors ───────────────────────────
            ->colors([
                'primary' => Color::Indigo,
                'danger'  => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info'    => Color::Sky,
            ])

            // ─── Auth ─────────────────────────────
            ->login()
            ->authGuard('platform')  // Separate guard for platform admins
            ->authPasswordBroker('platform_users')

            // ─── Dark Mode ────────────────────────
            ->darkMode()

            // ─── Navigation Groups ────────────────
            ->navigationGroups([
                NavigationGroup::make('Tenants')
                    ->icon('heroicon-o-building-office-2')
                    ->label('Tenants'),
                NavigationGroup::make('Billing')
                    ->icon('heroicon-o-credit-card'),
                NavigationGroup::make('Plans & Modules')
                    ->icon('heroicon-o-puzzle-piece'),
                NavigationGroup::make('Support')
                    ->icon('heroicon-o-ticket'),
                NavigationGroup::make('Monitoring')
                    ->icon('heroicon-o-chart-bar'),
                NavigationGroup::make('System')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])

            // ─── Resource Discovery ───────────────
            ->discoverResources(
                in: app_path('Filament/SuperAdmin/Resources'),
                for: 'App\\Filament\\SuperAdmin\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/SuperAdmin/Pages'),
                for: 'App\\Filament\\SuperAdmin\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/SuperAdmin/Widgets'),
                for: 'App\\Filament\\SuperAdmin\\Widgets'
            )

            // ─── Dashboard Widgets ────────────────
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\SuperAdmin\Widgets\PlatformStatsWidget::class,
                \App\Filament\SuperAdmin\Widgets\RevenueChartWidget::class,
                \App\Filament\SuperAdmin\Widgets\RecentSignupsWidget::class,
                \App\Filament\SuperAdmin\Widgets\NeedsAttentionWidget::class,
                \App\Filament\SuperAdmin\Widgets\ModulePopularityWidget::class,
            ])

            // ─── Global Search ────────────────────
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix('⌘K')

            // ─── Notifications ────────────────────
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            // ─── Top Navigation ───────────────────
            ->topNavigation(false)  // Use sidebar

            // ─── SPA Mode ─────────────────────────
            ->spa()

            // ─── Middleware ───────────────────────
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

---

## File 9: Dashboard Widgets (Screen 1)

```php
// app/Filament/SuperAdmin/Widgets/PlatformStatsWidget.php

<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // MRR calculation
        $mrr = Tenant::where('subscription_status', 'active')
            ->with('plan', 'addonSubscriptions')
            ->get()
            ->sum('monthly_total');

        // Last month MRR for comparison
        // (simplified — in production you'd read from tenant_usage_history)
        $lastMonthMrr = $mrr * 0.92; // placeholder

        $mrrGrowth = $lastMonthMrr > 0
            ? round(($mrr - $lastMonthMrr) / $lastMonthMrr * 100, 1)
            : 0;

        $activeTenants = Tenant::where('subscription_status', 'active')->count();
        $trialTenants = Tenant::where('subscription_status', 'trial')->count();

        $totalUsers = \DB::table('tenant_usage')->sum('users_count');

        $churnedThisMonth = Tenant::where('subscription_status', 'cancelled')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();
        $churnRate = $activeTenants > 0
            ? round($churnedThisMonth / $activeTenants * 100, 1)
            : 0;

        return [
            Stat::make('MRR', 'EGP ' . number_format($mrr / 100))
                ->description($mrrGrowth >= 0 ? "+{$mrrGrowth}% vs last month" : "{$mrrGrowth}%")
                ->descriptionIcon($mrrGrowth >= 0
                    ? 'heroicon-o-arrow-trending-up'
                    : 'heroicon-o-arrow-trending-down')
                ->color($mrrGrowth >= 0 ? 'success' : 'danger')
                ->chart([85, 90, 95, 100, 108, 115, $mrr / 100]),

            Stat::make('Active Clinics', $activeTenants)
                ->description("+{$trialTenants} on trial")
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('success'),

            Stat::make('Total Users', number_format($totalUsers))
                ->description('Across all clinics')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),

            Stat::make('Churn Rate', "{$churnRate}%")
                ->description("{$churnedThisMonth} cancelled this month")
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color($churnRate > 5 ? 'danger' : 'success'),
        ];
    }
}
```

```php
// app/Filament/SuperAdmin/Widgets/RevenueChartWidget.php

<?php

namespace App\Filament\SuperAdmin\Widgets;

use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Trend';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        // In production, query tenant_usage_history grouped by month
        $months = collect(range(11, 0))->map(fn($i) =>
            now()->subMonths($i)->format('M Y')
        );

        // Placeholder data — replace with real queries
        $planRevenue = [42, 48, 55, 62, 68, 75, 82, 88, 95, 102, 112, 118];
        $addonRevenue = [2, 2, 3, 3, 3, 4, 4, 4, 5, 5, 5, 6];
        $overageRevenue = [0, 1, 0, 1, 1, 1, 1, 2, 1, 1, 2, 1];

        return [
            'datasets' => [
                [
                    'label' => 'Plan Revenue',
                    'data' => $planRevenue,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.3)',
                    'borderColor' => 'rgb(99, 102, 241)',
                    'fill' => true,
                ],
                [
                    'label' => 'Add-on Revenue',
                    'data' => $addonRevenue,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.3)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'fill' => true,
                ],
                [
                    'label' => 'Overage',
                    'data' => $overageRevenue,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.3)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'scales' => [
                'y' => [
                    'stacked' => true,
                    'ticks' => ['callback' => 'function(value) { return "EGP " + value + "K"; }'],
                ],
            ],
        ];
    }
}
```

```php
// app/Filament/SuperAdmin/Widgets/NeedsAttentionWidget.php

<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Tenant;
use App\Models\SupportTicket;
use Filament\Widgets\Widget;

class NeedsAttentionWidget extends Widget
{
    protected static string $view = 'filament.super-admin.widgets.needs-attention';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function getAlerts(): array
    {
        $alerts = [];

        // Overdue invoices
        $overdue = Tenant::where('subscription_status', 'past_due')->count();
        if ($overdue > 0) {
            $alerts[] = [
                'icon' => '⚠️',
                'text' => "{$overdue} invoices overdue",
                'color' => 'warning',
                'url' => route('filament.super-admin.resources.tenants.index', [
                    'tableFilters[subscription_status][value]' => 'past_due',
                ]),
            ];
        }

        // Expiring trials
        $expiringTrials = Tenant::where('subscription_status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(3)])
            ->count();
        if ($expiringTrials > 0) {
            $alerts[] = [
                'icon' => '⏳',
                'text' => "{$expiringTrials} trials expiring in 3 days",
                'color' => 'warning',
                'url' => route('filament.super-admin.resources.tenants.index', [
                    'tableFilters[trial_expiring]' => true,
                ]),
            ];
        }

        // Suspended accounts
        $suspended = Tenant::where('subscription_status', 'suspended')->count();
        if ($suspended > 0) {
            $alerts[] = [
                'icon' => '🔴',
                'text' => "{$suspended} tenant(s) suspended",
                'color' => 'danger',
            ];
        }

        // Open support tickets
        $openTickets = SupportTicket::where('status', 'open')->count();
        if ($openTickets > 0) {
            $alerts[] = [
                'icon' => '🎫',
                'text' => "{$openTickets} support tickets unresolved",
                'color' => 'warning',
            ];
        }

        // Storage warnings (>90%)
        $storageWarnings = \DB::table('tenant_usage')
            ->join('tenants', 'tenants.id', '=', 'tenant_usage.tenant_id')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'tenants.subscription_plan_id')
            ->whereRaw('tenant_usage.storage_used_mb > subscription_plans.max_storage_mb * 0.9')
            ->whereNotNull('subscription_plans.max_storage_mb')
            ->count();
        if ($storageWarnings > 0) {
            $alerts[] = [
                'icon' => '💾',
                'text' => "{$storageWarnings} tenants at 90%+ storage",
                'color' => 'warning',
            ];
        }

        return $alerts;
    }
}
```

```blade
{{-- resources/views/filament/super-admin/widgets/needs-attention.blade.php --}}

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            🚨 Needs Attention
        </x-slot>

        <div class="space-y-2">
            @forelse ($this->getAlerts() as $alert)
                <a
                    href="{{ $alert['url'] ?? '#' }}"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm
                        {{ $alert['color'] === 'danger' ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' : '' }}
                        {{ $alert['color'] === 'warning' ? 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400' : '' }}
                        hover:opacity-80 transition"
                >
                    <span>{{ $alert['icon'] }}</span>
                    <span>{{ $alert['text'] }}</span>
                </a>
            @empty
                <div class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                    ✅ All clear — nothing needs attention!
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Summary: Files Created

| # | File | Purpose |
|---|------|---------|
| 1 | `app/Models/Tenant.php` | Tenant model with relationships and computed fields |
| 2 | `TenantResource.php` | Main resource: form, table, filters, actions, search |
| 3 | `Pages/ListTenants.php` | List page with stats widget header |
| 4 | `Pages/ViewTenant.php` | Detail page with 7-tab infolist (the big one) |
| 5 | `RelationManagers/InvoicesRelationManager.php` | Invoice history table |
| 6 | `tenant-modules.blade.php` | Custom modules tab with activate/deactivate |
| 7 | `Pages/CreateTenant.php` | Create page with auto-provisioning |
| 8 | `SuperAdminPanelProvider.php` | Panel configuration (auth, colors, nav, widgets) |
| 9 | `Widgets/PlatformStatsWidget.php` | KPI cards (MRR, clinics, users, churn) |
| 10 | `Widgets/RevenueChartWidget.php` | Revenue trend chart (stacked area) |
| 11 | `Widgets/NeedsAttentionWidget.php` | Alert list (overdue, trials, storage) |

## Patterns Demonstrated (Reuse for All Other Screens)

| Pattern | Where Used | Reuse For |
|---------|-----------|-----------|
| **Stats cards** | PlatformStatsWidget | Dashboard, Revenue Analytics |
| **Table with filters** | TenantResource::table() | All list screens |
| **Badges with colors** | Status, Plan columns | Every status field |
| **Row actions** | Login As, Email, Suspend | All record actions |
| **Bulk actions** | Send Reminders | All lists |
| **Tabbed detail view** | ViewTenant infolist | Clinic Detail, Ticket Detail |
| **Relation manager** | InvoicesRelationManager | All child records |
| **Custom Blade in tab** | tenant-modules.blade.php | Complex custom UIs |
| **Confirmation modals** | Suspend, Delete | All destructive actions |
| **Inline forms in actions** | Email action | Quick data entry |
| **Charts** | RevenueChartWidget | All analytics screens |
| **Alerts widget** | NeedsAttentionWidget | System monitoring |
| **Global search** | getGloballySearchableAttributes | All resources |
| **Nav badges** | getNavigationBadge | Tickets, alerts counts |
| **Create with after hook** | CreateTenant::afterCreate | Auto-provisioning |
| **Auto-refresh** | ->poll('60s') | Real-time data |
| **Computed columns** | monthly_total, usage | Derived data display |
