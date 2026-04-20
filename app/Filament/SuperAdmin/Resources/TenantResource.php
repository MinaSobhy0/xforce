<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TenantResource\Pages;
use App\Filament\SuperAdmin\Resources\TenantResource\RelationManagers;
use App\Filament\SuperAdmin\Resources\TenantResource\Widgets;
use App\Models\SubscriptionPlan;
use Modules\Core\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Clinics';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    // Global Search
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'domain', 'contact_name', 'contact_email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Plan'   => $record->plan?->code ?? $record->subscription_plan ?? '-',
            'Status' => $record->subscription_status ?? $record->status,
            'Owner'  => $record->contact_name ?? '-',
        ];
    }

    // CREATE / EDIT FORM
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
                        ->afterStateUpdated(function (string $state, Forms\Set $set, Forms\Get $get, ?Tenant $record) {
                            // Only auto-fill on creation when fields are empty
                            if (!$record) {
                                if (empty($get('slug'))) {
                                    $set('slug', Str::slug($state));
                                }
                                if (empty($get('database_name'))) {
                                    $set('database_name', 'tenant_' . Str::slug($state, '_'));
                                }
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label('Subdomain')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->prefix('https://')
                        ->suffix('.xforcehr.com')
                        ->helperText('This will be the clinic\'s URL'),

                    Forms\Components\TextInput::make('database_name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->disabled(function (?Tenant $record): bool {
                            if (!$record) {
                                return false; // Editable when creating
                            }
                            // Check if schema exists
                            $schemaExists = \DB::select(
                                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                                [$record->database_name]
                            );
                            return !empty($schemaExists); // Disabled if schema exists
                        })
                        ->helperText(function (?Tenant $record): ?string {
                            if (!$record) {
                                return null;
                            }
                            $schemaExists = \DB::select(
                                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                                [$record->database_name]
                            );
                            return !empty($schemaExists) ? 'Schema already created - cannot change' : 'Editable until schema is provisioned';
                        })
                        ->dehydrated(),

                    Forms\Components\TextInput::make('domain')
                        ->label('Custom Domain')
                        ->placeholder('clinic.example.com')
                        ->helperText('Optional custom domain'),
                ]),

            Forms\Components\Section::make('Owner')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('contact_name')
                        ->label('Owner Name')
                        ->required(),

                    Forms\Components\TextInput::make('contact_email')
                        ->label('Email')
                        ->email()
                        ->required(),

                    Forms\Components\TextInput::make('contact_phone')
                        ->label('Phone')
                        ->tel(),
                ]),

            Forms\Components\Section::make('Subscription')
                ->icon('heroicon-o-credit-card')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('subscription_plan_id')
                        ->label('Plan')
                        ->options(fn() => SubscriptionPlan::active()->ordered()->pluck('code', 'id'))
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('subscription_status')
                        ->options([
                            'trial'     => 'Trial',
                            'active'    => 'Active',
                            'past_due'  => 'Past Due',
                            'suspended' => 'Suspended',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('trial')
                        ->required(),

                    Forms\Components\DateTimePicker::make('trial_ends_at')
                        ->label('Trial Ends')
                        ->default(now()->addDays(14))
                        ->visible(fn(Forms\Get $get) => $get('subscription_status') === 'trial'),

                    Forms\Components\DateTimePicker::make('subscription_expires_at')
                        ->label('Subscription Ends'),
                ]),

            Forms\Components\Section::make('Localization')
                ->icon('heroicon-o-globe-alt')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('country')
                        ->label('Country')
                        ->options([
                            'EG' => 'Egypt',
                            'SA' => 'Saudi Arabia',
                            'AE' => 'UAE',
                            'KW' => 'Kuwait',
                            'QA' => 'Qatar',
                            'BH' => 'Bahrain',
                            'OM' => 'Oman',
                            'JO' => 'Jordan',
                            'LB' => 'Lebanon',
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

                    Forms\Components\Select::make('currency')
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

            Forms\Components\Section::make('Resource Limits')
                ->icon('heroicon-o-adjustments-horizontal')
                ->description('Limits are determined by the subscription plan. You can add extra resources below. Patients, treatments, equipment, and products are unlimited for all plans.')
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('plan_limits_info')
                        ->label('Plan Limits')
                        ->content(function ($record) {
                            if (!$record?->plan) {
                                return 'No plan selected - using default limits';
                            }
                            $plan = $record->plan;
                            return "Users: {$plan->max_users} | Branches: {$plan->max_branches} | Storage: {$plan->max_storage_mb} MB";
                        })
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('extra_users')
                        ->label('Extra Users')
                        ->helperText(fn ($record) => $record?->plan
                            ? 'Total: ' . (($record->plan->max_users ?? 0) + ($record->extra_users ?? 0)) . ' users'
                            : 'Plan limit + this extra amount')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Forms\Components\TextInput::make('extra_branches')
                        ->label('Extra Branches')
                        ->helperText(fn ($record) => $record?->plan
                            ? 'Total: ' . (($record->plan->max_branches ?? 0) + ($record->extra_branches ?? 0)) . ' branches'
                            : 'Plan limit + this extra amount')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Forms\Components\TextInput::make('extra_storage_mb')
                        ->label('Extra Storage (MB)')
                        ->helperText(fn ($record) => $record?->plan
                            ? 'Total: ' . (($record->plan->max_storage_mb ?? 0) + ($record->extra_storage_mb ?? 0)) . ' MB'
                            : 'Plan limit + this extra amount')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('MB'),
                ]),

            Forms\Components\Section::make('Custom Pricing')
                ->icon('heroicon-o-currency-dollar')
                ->description('Override platform default pricing for this tenant. Leave empty to use platform defaults.')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('extra_user_price')
                        ->label('Price per Extra User (EGP/month)')
                        ->helperText(fn () => 'Platform default: EGP ' . \App\Models\PlatformSetting::get('extra_user_price_egp', 50))
                        ->numeric()
                        ->minValue(0)
                        ->prefix('EGP')
                        ->placeholder('Use platform default'),
                    Forms\Components\TextInput::make('extra_branch_price')
                        ->label('Price per Extra Branch (EGP/month)')
                        ->helperText(fn () => 'Platform default: EGP ' . \App\Models\PlatformSetting::get('extra_branch_price_egp', 100))
                        ->numeric()
                        ->minValue(0)
                        ->prefix('EGP')
                        ->placeholder('Use platform default'),
                ]),

            Forms\Components\Section::make('Branding')
                ->icon('heroicon-o-paint-brush')
                ->description('Customize the clinic\'s visual identity')
                ->columns(2)
                ->collapsible()
                ->schema([
                    Forms\Components\FileUpload::make('logo_path')
                        ->label('Logo')
                        ->image()
                        ->directory('tenants/logos')
                        ->visibility('public')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->helperText('Any aspect ratio, max 2MB. Used in navigation and emails.'),

                    Forms\Components\FileUpload::make('favicon_path')
                        ->label('Favicon')
                        ->image()
                        ->directory('tenants/favicons')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml'])
                        ->maxSize(256)
                        ->helperText('ICO, PNG, or SVG. Max 256KB.'),

                    Forms\Components\ColorPicker::make('primary_color')
                        ->label('Primary Color')
                        ->default('#3B82F6')
                        ->helperText('Main brand color used for buttons and links'),

                    Forms\Components\ColorPicker::make('secondary_color')
                        ->label('Secondary Color')
                        ->default('#10B981')
                        ->helperText('Accent color for highlights'),
                ]),
        ]);
    }

    // TABLE — Clinics List (Screen 2)
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Clinic Info Column
                Tables\Columns\TextColumn::make('name')
                    ->label('Clinic')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn(Tenant $record): string =>
                        $record->slug . '.xforcehr.com'
                    ),

                // Plan Column
                Tables\Columns\TextColumn::make('plan.code')
                    ->label('Plan')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'enterprise'   => 'success',
                        'professional' => 'info',
                        'starter'      => 'warning',
                        default        => 'gray',
                    })
                    ->default(fn(Tenant $record) => $record->subscription_plan)
                    ->sortable(),

                // Status Column
                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'active'    => 'success',
                        'trial'     => 'info',
                        'past_due'  => 'warning',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(function (?string $state, Tenant $record): string {
                        if ($state === 'trial' && $record->trial_ends_at) {
                            $days = now()->diffInDays($record->trial_ends_at, false);
                            if ($days > 0) {
                                return "Trial ({$days}d left)";
                            }
                        }
                        return ucfirst(str_replace('_', ' ', $state ?? 'pending'));
                    })
                    ->sortable(),

                // Users Count
                Tables\Columns\TextColumn::make('usage.users')
                    ->label('Users')
                    ->formatStateUsing(function ($state, Tenant $record): string {
                        $planLimit = $record->plan?->max_users ?? 0;
                        $extra = $record->extra_users ?? 0;
                        $total = $planLimit + $extra;
                        $limitStr = $total > 0 ? $total : '∞';
                        return ($state ?? 0) . '/' . $limitStr;
                    })
                    ->color(function ($state, Tenant $record): string {
                        $planLimit = $record->plan?->max_users ?? 0;
                        $extra = $record->extra_users ?? 0;
                        $total = $planLimit + $extra;
                        if ($total <= 0) return 'gray';
                        $pct = ($state ?? 0) / $total * 100;
                        if ($pct >= 90) return 'danger';
                        if ($pct >= 70) return 'warning';
                        return 'gray';
                    }),

                // Patients Count
                Tables\Columns\TextColumn::make('usage.patients')
                    ->label('Patients')
                    ->numeric()
                    ->sortable(),

                // MRR Column
                Tables\Columns\TextColumn::make('plan.price_monthly_minor')
                    ->label('MRR')
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                // Country
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'EG' => '🇪🇬',
                        'SA' => '🇸🇦',
                        'AE' => '🇦🇪',
                        'KW' => '🇰🇼',
                        default => $state ?? '-',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Created Date
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            // Filters
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
                    ->options(fn() => SubscriptionPlan::active()->ordered()->pluck('code', 'id')),

                Tables\Filters\SelectFilter::make('country')
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

            // Row Actions
            ->actions([
                Tables\Actions\Action::make('loginAs')
                    ->label('Login As')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('info')
                    ->visible(fn(Tenant $record) => self::schemaExists($record))
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Select User')
                            ->options(function (Tenant $record) {
                                try {
                                    $schemaName = $record->database_name;
                                    \DB::statement("SET search_path TO \"{$schemaName}\"");

                                    $users = \DB::table('users')
                                        ->select('id', 'first_name', 'last_name', 'email', 'status')
                                        ->orderBy('first_name')
                                        ->get();

                                    \DB::statement("SET search_path TO public");

                                    return $users->mapWithKeys(function ($user) {
                                        $name = trim("{$user->first_name} {$user->last_name}");
                                        $status = $user->status !== 'active' ? " [{$user->status}]" : '';
                                        return [$user->id => "{$name} ({$user->email}){$status}"];
                                    });
                                } catch (\Exception $e) {
                                    \DB::statement("SET search_path TO public");
                                    return [];
                                }
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data) {
                        try {
                            $schemaName = $record->database_name;
                            \DB::statement("SET search_path TO \"{$schemaName}\"");

                            // SECURITY: Generate cryptographically secure random token
                            $token = \Illuminate\Support\Str::random(64);
                            $expiresAt = now()->addMinutes(5);

                            // SECURITY: Use bcrypt for token storage (slow hash to resist brute-force)
                            \DB::table('users')
                                ->where('id', $data['user_id'])
                                ->update([
                                    'impersonation_token' => password_hash($token, PASSWORD_BCRYPT),
                                    'impersonation_token_expires_at' => $expiresAt,
                                ]);

                            \DB::statement("SET search_path TO public");

                            $url = "https://{$record->slug}.xforcehr.com/admin/impersonate?token={$token}&user={$data['user_id']}";

                            return redirect()->away($url);

                        } catch (\Exception $e) {
                            \DB::statement("SET search_path TO public");

                            Notification::make()
                                ->title('Failed to generate login link')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

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
                        Notification::make()
                            ->title('Email sent to ' . ($record->contact_name ?? $record->name))
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
                                'status' => 'suspended',
                            ]);
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
                                'status' => 'active',
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

            // Bulk Actions
            ->bulkActions([
                Tables\Actions\BulkAction::make('sendReminders')
                    ->label('Send Payment Reminders')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
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

            // Table Config
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->poll('60s');
    }

    // PAGES
    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
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
            'mobile-app' => Pages\ManageTenantMobileApp::route('/{record}/mobile-app'),
        ];
    }

    // NAVIGATION BADGE
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::whereIn('status', ['active', 'pending'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    /**
     * Check if a tenant's database schema exists.
     */
    protected static function schemaExists(Tenant $tenant): bool
    {
        if (!$tenant->database_name) {
            return false;
        }

        try {
            $result = \DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$tenant->database_name]
            );
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
