<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Models\AddOn;
use App\Models\SubscriptionPlan;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\TenantService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ViewTenant extends BaseViewRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Mount the page and compute tenant usage.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Compute and update usage statistics
        $this->record->computeUsage();

        // Refresh the record to get the updated usage
        $this->record->load('usage');
    }

    /**
     * Get list of tenant backups from storage.
     */
    public function getTenantBackups(): array
    {
        $tenantId = $this->record->id;
        $backupsPath = "backups/tenants/{$tenantId}";
        $backups = [];

        if (Storage::disk('local')->exists($backupsPath)) {
            $directories = Storage::disk('local')->directories($backupsPath);

            foreach ($directories as $dir) {
                $manifestPath = "{$dir}/manifest.json";
                if (Storage::disk('local')->exists($manifestPath)) {
                    $manifest = json_decode(Storage::disk('local')->get($manifestPath), true);

                    $totalSize = 0;
                    $components = [];
                    foreach ($manifest['components'] ?? [] as $name => $info) {
                        $components[] = ucfirst($name);
                        $totalSize += $info['size'] ?? 0;
                    }

                    $backups[] = [
                        'path' => $dir,
                        'timestamp' => basename($dir),
                        'created_at' => $manifest['created_at'] ?? null,
                        'components' => implode(', ', $components),
                        'size' => $this->formatBytes($totalSize),
                        'version' => $manifest['version'] ?? 'Unknown',
                    ];
                }
            }

            usort($backups, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
        }

        return $backups;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Restore a backup for this tenant.
     */
    public function restoreBackup(string $path): void
    {
        try {
            $exitCode = Artisan::call('backup:restore', [
                'path' => $path,
                '--tenant' => $this->record->id,
                '--force' => true,
            ]);

            $output = Artisan::output();

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Backup restored successfully')
                    ->body('The tenant database has been restored from the selected backup.')
                    ->success()
                    ->send();
            } else {
                Log::error('Backup restore command failed', [
                    'tenant_id' => $this->record->id,
                    'path' => $path,
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]);

                Notification::make()
                    ->title('Restore failed')
                    ->body('Command failed with exit code: ' . $exitCode)
                    ->danger()
                    ->persistent()
                    ->send();
            }

        } catch (\Exception $e) {
            Log::error('Backup restore failed', [
                'tenant_id' => $this->record->id,
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Restore failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Delete a backup for this tenant.
     */
    public function deleteBackup(string $path): void
    {
        try {
            // Verify path belongs to this tenant
            if (!str_contains($path, "backups/tenants/{$this->record->id}/")) {
                throw new \Exception('Invalid backup path');
            }

            Storage::disk('local')->deleteDirectory($path);

            Notification::make()
                ->title('Backup deleted')
                ->body('The backup has been permanently deleted.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Delete failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // Top Action Buttons
    protected function getViewHeaderActions(): array
    {
        return [
            // Login As - Primary support action (always visible)
            Actions\Action::make('loginAs')
                ->label('Login As')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('info')
                ->visible(fn() => $this->schemaExists())
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Select User')
                        ->options(function () {
                            try {
                                $schemaName = $this->record->database_name;
                                DB::statement("SET search_path TO \"{$schemaName}\"");

                                $users = DB::table('users')
                                    ->select('id', 'first_name', 'last_name', 'email', 'status')
                                    ->orderBy('first_name')
                                    ->get();

                                DB::statement("SET search_path TO public");

                                return $users->mapWithKeys(function ($user) {
                                    $name = trim("{$user->first_name} {$user->last_name}");
                                    $status = $user->status !== 'active' ? " [{$user->status}]" : '';
                                    return [$user->id => "{$name} ({$user->email}){$status}"];
                                });
                            } catch (\Exception $e) {
                                DB::statement("SET search_path TO public");
                                return [];
                            }
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $schemaName = $this->record->database_name;
                        DB::statement("SET search_path TO \"{$schemaName}\"");

                        $token = \Illuminate\Support\Str::random(64);
                        $expiresAt = now()->addMinutes(5);

                        DB::table('users')
                            ->where('id', $data['user_id'])
                            ->update([
                                'impersonation_token' => password_hash($token, PASSWORD_BCRYPT),
                                'impersonation_token_expires_at' => $expiresAt,
                            ]);

                        DB::statement("SET search_path TO public");

                        $url = "https://{$this->record->slug}.x-linic.com/admin/impersonate?token={$token}&user={$data['user_id']}";

                        $this->js("window.open('{$url}', '_blank')");

                    } catch (\Exception $e) {
                        DB::statement("SET search_path TO public");

                        Notification::make()
                            ->title('Failed to generate login link')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // More Actions Dropdown - All other actions grouped here
            Actions\ActionGroup::make([
                // Database & Setup
                Actions\Action::make('provisionDatabase')
                    ->label('Provision Database')
                    ->icon('heroicon-o-server-stack')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Provision Tenant Database')
                    ->modalDescription(fn() => "This will create the PostgreSQL schema '{$this->record->database_name}', run all migrations, and create an owner user with email '{$this->record->contact_email}'. Continue?")
                    ->modalSubmitActionLabel('Yes, provision database')
                    ->visible(fn() => !$this->schemaExists())
                    ->action(function (): void {
                        try {
                            $tenantService = app(TenantService::class);
                            $tenantService->createTenantDatabase($this->record);

                            DB::statement("SET search_path TO public");
                            DB::purge('pgsql');
                            DB::reconnect('pgsql');

                            $this->record->refresh();
                            $ownerCreated = $this->record->owner_user_id !== null;

                            $body = "Schema '{$this->record->database_name}' has been created and migrations have been run.";
                            if ($ownerCreated && $this->record->contact_email) {
                                $body .= "\n\nOwner account created:\nEmail: {$this->record->contact_email}\nLogin URL: https://{$this->record->slug}.x-linic.com/admin";
                            }

                            Notification::make()
                                ->title('Database provisioned successfully')
                                ->body($body)
                                ->success()
                                ->persistent()
                                ->send();

                            $this->refreshFormData(['database_name']);

                        } catch (\Exception $e) {
                            try {
                                DB::statement("SET search_path TO public");
                                DB::purge('pgsql');
                                DB::reconnect('pgsql');
                            } catch (\Exception $ignored) {}

                            Log::error('Tenant database provisioning failed', [
                                'tenant_id' => $this->record->id,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Database provisioning failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Actions\Action::make('createOwnerUser')
                    ->label('Create/Reset Owner')
                    ->icon('heroicon-o-user-plus')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Create or Reset Owner User')
                    ->modalDescription(fn() => $this->record->owner_user_id
                        ? "This will reset the password for the owner user ({$this->record->contact_email}). A new password will be generated."
                        : "This will create an owner user with email '{$this->record->contact_email}'.")
                    ->modalSubmitActionLabel('Create/Reset Owner')
                    ->visible(fn() => $this->schemaExists() && $this->record->contact_email)
                    ->action(function (): void {
                        try {
                            $tenantService = app(TenantService::class);
                            $result = $tenantService->createOwnerUser($this->record);

                            DB::statement("SET search_path TO public");
                            DB::purge('pgsql');
                            DB::reconnect('pgsql');

                            $this->record->refresh();

                            if ($result && $result['password']) {
                                Notification::make()
                                    ->title('Owner user created/reset')
                                    ->body("Email: {$this->record->contact_email}\nPassword: {$result['password']}\n\nPassword is also visible in the Info tab.")
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } elseif ($result) {
                                Notification::make()
                                    ->title('Owner user already exists')
                                    ->body("User with email {$this->record->contact_email} already exists.")
                                    ->info()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to create owner user')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            DB::statement("SET search_path TO public");

                            Notification::make()
                                ->title('Failed to create owner user')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\Action::make('resetDatabase')
                    ->label('Reset Database')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Tenant Database')
                    ->modalDescription(fn() => "WARNING: This will DROP the schema '{$this->record->database_name}' and ALL its data, then recreate it with fresh migrations. This action cannot be undone!")
                    ->modalSubmitActionLabel('Yes, reset database')
                    ->visible(fn() => $this->schemaExists())
                    ->action(function (): void {
                        try {
                            $tenantService = app(TenantService::class);

                            $tenantService->dropTenantDatabase($this->record);
                            $tenantService->createTenantDatabase($this->record);

                            DB::statement("SET search_path TO public");
                            DB::purge('pgsql');
                            DB::reconnect('pgsql');

                            Notification::make()
                                ->title('Database reset successfully')
                                ->body("Schema '{$this->record->database_name}' has been dropped and recreated.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            try {
                                DB::statement("SET search_path TO public");
                                DB::purge('pgsql');
                                DB::reconnect('pgsql');
                            } catch (\Exception $ignored) {}

                            Log::error('Tenant database reset failed', [
                                'tenant_id' => $this->record->id,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Database reset failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                // Communication
                Actions\Action::make('emailOwner')
                    ->label('Send Email')
                    ->icon('heroicon-o-envelope')
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

                // Billing Actions
                Actions\Action::make('changePlan')
                    ->label('Change Plan')
                    ->icon('heroicon-o-credit-card')
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

                // Status Actions
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

                        Notification::make()
                            ->title('Clinic reactivated')
                            ->success()
                            ->send();
                    }),
            ])
                ->label('More Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button(),

            // Mobile App - Keep visible
            Actions\Action::make('configureMobileApp')
                ->label('Mobile App')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('info')
                ->url(fn () => TenantResource::getUrl('mobile-app', ['record' => $this->record])),

            // Edit - Standard action
            Actions\EditAction::make(),

            // Delete - Danger action kept visible
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
                                    "{$state}.x-linic.com"
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
                                            ->color(fn() => $this->schemaExists() ? 'success' : 'danger')
                                            ->icon(fn() => $this->schemaExists() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                            ->formatStateUsing(fn($state) => $state . ($this->schemaExists() ? ' (provisioned)' : ' (not provisioned)')),
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
                                        Components\TextEntry::make('settings.initial_owner_password')
                                            ->label('Initial Password')
                                            ->copyable()
                                            ->copyMessage('Password copied!')
                                            ->icon('heroicon-o-key')
                                            ->color('warning')
                                            ->default('Not set'),
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
                                ->description('Limits from subscription plan + additional purchased')
                                ->columns(3)
                                ->schema([
                                    Components\TextEntry::make('usage.users')
                                        ->label('Users')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $current = (int) ($state ?? 0);
                                            $planLimit = $record->plan?->max_users ?? 0;
                                            $extra = $record->extra_users ?? 0;
                                            $total = $planLimit + $extra;

                                            if ($total <= 0) return "{$current} / ∞";

                                            $label = "{$current} / {$total}";
                                            if ($extra > 0) {
                                                $label .= " ({$planLimit} + {$extra})";
                                            }
                                            return $label;
                                        })
                                        ->color(function ($state, Tenant $record) {
                                            $planLimit = $record->plan?->max_users ?? 0;
                                            $extra = $record->extra_users ?? 0;
                                            $total = $planLimit + $extra;
                                            if ($total <= 0) return 'gray';
                                            $pct = ((int) ($state ?? 0)) / $total * 100;
                                            if ($pct >= 90) return 'danger';
                                            if ($pct >= 70) return 'warning';
                                            return 'success';
                                        }),

                                    Components\TextEntry::make('usage.branches')
                                        ->label('Branches')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $current = (int) ($state ?? 0);
                                            $planLimit = $record->plan?->max_branches ?? 0;
                                            $extra = $record->extra_branches ?? 0;
                                            $total = $planLimit + $extra;

                                            if ($total <= 0) return "{$current} / ∞";

                                            $label = "{$current} / {$total}";
                                            if ($extra > 0) {
                                                $label .= " ({$planLimit} + {$extra})";
                                            }
                                            return $label;
                                        })
                                        ->color(function ($state, Tenant $record) {
                                            $planLimit = $record->plan?->max_branches ?? 0;
                                            $extra = $record->extra_branches ?? 0;
                                            $total = $planLimit + $extra;
                                            if ($total <= 0) return 'gray';
                                            $pct = ((int) ($state ?? 0)) / $total * 100;
                                            if ($pct >= 90) return 'danger';
                                            if ($pct >= 70) return 'warning';
                                            return 'success';
                                        }),

                                    Components\TextEntry::make('usage.storage_mb')
                                        ->label('Storage')
                                        ->formatStateUsing(function ($state, Tenant $record) {
                                            $used = round(((float) ($state ?? 0)) / 1024, 2);
                                            $planLimit = $record->plan?->max_storage_mb ?? 0;
                                            $extra = $record->extra_storage_mb ?? 0;
                                            $total = $planLimit + $extra;

                                            if ($total <= 0) return "{$used} GB / ∞";

                                            $limitGb = round($total / 1024, 1);
                                            return "{$used} GB / {$limitGb} GB";
                                        })
                                        ->color(function ($state, Tenant $record) {
                                            $planLimit = $record->plan?->max_storage_mb ?? 0;
                                            $extra = $record->extra_storage_mb ?? 0;
                                            $total = $planLimit + $extra;
                                            if ($total <= 0) return 'gray';
                                            $pct = ((float) ($state ?? 0)) / $total * 100;
                                            if ($pct >= 90) return 'danger';
                                            if ($pct >= 70) return 'warning';
                                            return 'success';
                                        }),
                                ]),

                            Components\Section::make('Unlimited Resources')
                                ->description('These resources have no limits')
                                ->columns(4)
                                ->schema([
                                    Components\TextEntry::make('usage.patients')
                                        ->label('Patients')
                                        ->formatStateUsing(fn($state) => ((int) ($state ?? 0)) . ' (unlimited)')
                                        ->color('success'),

                                    Components\TextEntry::make('usage.services')
                                        ->label('Services')
                                        ->formatStateUsing(fn($state) => ((int) ($state ?? 0)) . ' (unlimited)')
                                        ->color('success'),

                                    Components\TextEntry::make('usage.equipment')
                                        ->label('Equipment')
                                        ->formatStateUsing(fn($state) => ((int) ($state ?? 0)) . ' (unlimited)')
                                        ->color('success'),

                                    Components\TextEntry::make('usage.products')
                                        ->label('Products')
                                        ->formatStateUsing(fn($state) => ((int) ($state ?? 0)) . ' (unlimited)')
                                        ->color('success'),
                                ]),

                            Components\Section::make('Storage Breakdown')
                                ->columns(3)
                                ->schema([
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
                                        Components\Actions\Action::make('manageModules')
                                            ->label('Manage Modules')
                                            ->icon('heroicon-o-cog-6-tooth')
                                            ->color('primary')
                                            ->modalHeading('Manage Tenant Modules')
                                            ->modalDescription('Select which modules this tenant can access. Dependencies are validated automatically.')
                                            ->form(function () {
                                                $allModules = \App\Models\Module::whereRaw('is_active = true')
                                                    ->orderBy('category')
                                                    ->orderBy('sort_order')
                                                    ->get();

                                                // Load dependencies from module.json files
                                                $moduleDeps = $this->getModuleDependencies();

                                                $options = $allModules->mapWithKeys(function ($module) use ($moduleDeps) {
                                                    $emoji = $module->icon_emoji ?? '📦';
                                                    $category = ucfirst($module->category ?? 'other');
                                                    $deps = $moduleDeps[strtolower($module->code)] ?? [];
                                                    $depStr = !empty($deps) ? ' → requires: ' . implode(', ', $deps) : '';
                                                    return [$module->code => "{$emoji} {$module->name} ({$category}){$depStr}"];
                                                })->toArray();

                                                return [
                                                    Forms\Components\CheckboxList::make('modules')
                                                        ->label('Available Modules')
                                                        ->options($options)
                                                        ->columns(2)
                                                        ->searchable()
                                                        ->bulkToggleable()
                                                        ->descriptions([
                                                            'core' => 'Required - cannot be disabled',
                                                            'auth' => 'Required - cannot be disabled',
                                                        ]),
                                                ];
                                            })
                                            ->fillForm(fn (Tenant $record): array => [
                                                'modules' => $record->features ?? [],
                                            ])
                                            ->action(function (array $data, Tenant $record) {
                                                $selectedModules = $data['modules'] ?? [];

                                                // Always include core modules
                                                if (!in_array('core', $selectedModules)) {
                                                    $selectedModules[] = 'core';
                                                }
                                                if (!in_array('auth', $selectedModules)) {
                                                    $selectedModules[] = 'auth';
                                                }

                                                // Validate dependencies
                                                $moduleDeps = $this->getModuleDependencies();
                                                $errors = [];

                                                foreach ($selectedModules as $moduleCode) {
                                                    $deps = $moduleDeps[strtolower($moduleCode)] ?? [];
                                                    foreach ($deps as $dep) {
                                                        $depLower = strtolower($dep);
                                                        if (!in_array($depLower, array_map('strtolower', $selectedModules))) {
                                                            $errors[] = ucfirst($moduleCode) . " requires " . ucfirst($dep);
                                                        }
                                                    }
                                                }

                                                if (!empty($errors)) {
                                                    Notification::make()
                                                        ->title('Dependency Error')
                                                        ->body("Missing dependencies:\n• " . implode("\n• ", $errors))
                                                        ->danger()
                                                        ->persistent()
                                                        ->send();
                                                    return;
                                                }

                                                $record->update(['features' => $selectedModules]);

                                                Notification::make()
                                                    ->title('Modules updated')
                                                    ->body(count($selectedModules) . ' modules are now active for this tenant.')
                                                    ->success()
                                                    ->send();
                                            }),

                                        Components\Actions\Action::make('forceActivateAll')
                                            ->label('Activate All')
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
                                            ->label('Reset to Plan')
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

                                        Components\Actions\Action::make('deactivateAll')
                                            ->label('Deactivate All')
                                            ->icon('heroicon-o-x-circle')
                                            ->color('danger')
                                            ->requiresConfirmation()
                                            ->modalDescription('This will remove ALL modules from this tenant. Only core modules will remain accessible.')
                                            ->action(function (Tenant $record) {
                                                $record->update(['features' => ['core', 'auth']]);

                                                Notification::make()
                                                    ->title('All modules deactivated')
                                                    ->body('Only core modules remain active.')
                                                    ->warning()
                                                    ->send();
                                            }),
                                    ]),
                                ]),

                            Components\Section::make('Active Modules')
                                ->description(fn (Tenant $record) => count($record->features ?? []) . ' modules active')
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

                    // TAB 8: BACKUPS
                    Components\Tabs\Tab::make('Backups')
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            Components\Section::make('Backup Actions')
                                ->schema([
                                    Components\Actions::make([
                                        Components\Actions\Action::make('createBackup')
                                            ->label('Create Backup Now')
                                            ->icon('heroicon-o-arrow-down-tray')
                                            ->color('primary')
                                            ->requiresConfirmation()
                                            ->modalHeading('Create Tenant Backup')
                                            ->modalDescription('This will create a full backup of the tenant database and files. This may take a few minutes.')
                                            ->modalSubmitActionLabel('Start Backup')
                                            ->action(function (Tenant $record) {
                                                try {
                                                    Artisan::call('tenant:backup', [
                                                        'tenant' => $record->id,
                                                        '--compress' => true,
                                                    ]);

                                                    Notification::make()
                                                        ->title('Backup created successfully')
                                                        ->body('The backup has been created and stored.')
                                                        ->success()
                                                        ->send();

                                                } catch (\Exception $e) {
                                                    Log::error('Manual tenant backup failed', [
                                                        'tenant_id' => $record->id,
                                                        'error' => $e->getMessage(),
                                                    ]);

                                                    Notification::make()
                                                        ->title('Backup failed')
                                                        ->body($e->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),

                                        Components\Actions\Action::make('createDbOnlyBackup')
                                            ->label('Backup Database Only')
                                            ->icon('heroicon-o-circle-stack')
                                            ->color('gray')
                                            ->requiresConfirmation()
                                            ->action(function (Tenant $record) {
                                                try {
                                                    Artisan::call('tenant:backup', [
                                                        'tenant' => $record->id,
                                                        '--only-database' => true,
                                                        '--compress' => true,
                                                    ]);

                                                    Notification::make()
                                                        ->title('Database backup created')
                                                        ->success()
                                                        ->send();

                                                } catch (\Exception $e) {
                                                    Notification::make()
                                                        ->title('Backup failed')
                                                        ->body($e->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    ]),
                                ]),

                            Components\Section::make('Available Backups')
                                ->schema([
                                    Components\ViewEntry::make('backups_list')
                                        ->label('')
                                        ->view('filament.super-admin.components.tenant-backups-list'),
                                ]),
                        ]),
                ]),
        ]);
    }

    /**
     * Check if the tenant's database schema exists.
     */
    protected function schemaExists(): bool
    {
        if (!$this->record || !$this->record->database_name) {
            return false;
        }

        try {
            $result = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?",
                [$this->record->database_name]
            );

            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get module dependencies from module.json files.
     *
     * @return array<string, array<string>> Module code => [dependency codes]
     */
    protected function getModuleDependencies(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        $modulesPath = base_path('modules');

        if (!\Illuminate\Support\Facades\File::isDirectory($modulesPath)) {
            return $cache;
        }

        $directories = \Illuminate\Support\Facades\File::directories($modulesPath);

        foreach ($directories as $directory) {
            $moduleJsonPath = $directory . '/module.json';

            if (!\Illuminate\Support\Facades\File::exists($moduleJsonPath)) {
                continue;
            }

            try {
                $json = json_decode(\Illuminate\Support\Facades\File::get($moduleJsonPath), true);

                if (!$json) {
                    continue;
                }

                $code = strtolower($json['alias'] ?? $json['name'] ?? basename($directory));
                $dependencies = [];

                // Parse dependencies - can be array of strings or single string
                if (isset($json['dependencies'])) {
                    if (is_array($json['dependencies'])) {
                        foreach ($json['dependencies'] as $dep) {
                            if (is_string($dep)) {
                                $dependencies[] = strtolower($dep);
                            }
                        }
                    } elseif (is_string($json['dependencies'])) {
                        // Handle comma-separated or bracket format
                        $deps = preg_replace('/[\[\]]/', '', $json['dependencies']);
                        foreach (explode(',', $deps) as $dep) {
                            $dep = trim($dep);
                            if (!empty($dep)) {
                                $dependencies[] = strtolower($dep);
                            }
                        }
                    }
                }

                $cache[$code] = $dependencies;

            } catch (\Exception $e) {
                continue;
            }
        }

        return $cache;
    }
}
