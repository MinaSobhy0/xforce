<?php

namespace Modules\Auth\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\Role;
use Modules\Auth\Filament\Resources\RoleResource\Pages;
use Modules\Auth\Models\Permission;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Role::class;

    protected static ?string $moduleCode = 'auth';

    protected static ?string $permissionKey = 'roles';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('auth::auth.navigation.roles');
    }

    public static function getModelLabel(): string
    {
        return __('auth::auth.labels.role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('auth::auth.labels.roles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('auth::auth.sections.role_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('auth::auth.fields.name'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record?->is_system),

                        Forms\Components\TextInput::make('display_name')
                            ->label(__('auth::auth.fields.display_name'))
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->label(__('auth::auth.fields.description'))
                            ->rows(2),

                        Forms\Components\TextInput::make('level')
                            ->label(__('auth::auth.fields.level'))
                            ->numeric()
                            ->default(10)
                            ->helperText(__('auth::auth.helpers.role_level')),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('auth::auth.fields.is_active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('auth::auth.sections.permissions'))
                    ->description(__('auth::auth.sections.permissions_description'))
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('grant_all')
                                ->label(__('auth::auth.actions.grant_all'))
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(function (Forms\Set $set) {
                                    foreach (static::getResourcePermissions() as $resource => $label) {
                                        foreach (['view', 'create', 'update', 'delete', 'export', 'import'] as $action) {
                                            $set("permissions.{$resource}.{$action}", true);
                                        }
                                    }
                                }),
                            Forms\Components\Actions\Action::make('revoke_all')
                                ->label(__('auth::auth.actions.revoke_all'))
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->action(function (Forms\Set $set) {
                                    foreach (static::getResourcePermissions() as $resource => $label) {
                                        foreach (['view', 'create', 'update', 'delete', 'export', 'import'] as $action) {
                                            $set("permissions.{$resource}.{$action}", false);
                                        }
                                    }
                                }),
                        ])->columnSpanFull(),

                        ...static::getPermissionSchema(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected static function getPermissionSchema(): array
    {
        $groups = static::getGroupedResourcePermissions();
        $schema = [];

        foreach ($groups as $groupKey => $group) {
            $groupResources = array_keys($group['resources']);

            // Group actions (Grant All / Revoke All for this group)
            $groupActions = Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make("grant_all_{$groupKey}")
                    ->label(__('auth::auth.actions.grant_all'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('xs')
                    ->action(function (Forms\Set $set) use ($groupResources) {
                        foreach ($groupResources as $resource) {
                            foreach (['view', 'create', 'update', 'delete', 'export', 'import'] as $action) {
                                $set("permissions.{$resource}.{$action}", true);
                            }
                        }
                    }),
                Forms\Components\Actions\Action::make("revoke_all_{$groupKey}")
                    ->label(__('auth::auth.actions.revoke_all'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->size('xs')
                    ->action(function (Forms\Set $set) use ($groupResources) {
                        foreach ($groupResources as $resource) {
                            foreach (['view', 'create', 'update', 'delete', 'export', 'import'] as $action) {
                                $set("permissions.{$resource}.{$action}", false);
                            }
                        }
                    }),
            ])->columnSpanFull();

            $fieldsets = [$groupActions];

            foreach ($group['resources'] as $resource => $label) {
                $fieldsets[] = Forms\Components\Fieldset::make($label)
                    ->schema([
                        Forms\Components\Checkbox::make("permissions.{$resource}.view")
                            ->label(__('auth::auth.permissions.view'))
                            ->inline(),

                        Forms\Components\Checkbox::make("permissions.{$resource}.create")
                            ->label(__('auth::auth.permissions.create'))
                            ->inline(),

                        Forms\Components\Checkbox::make("permissions.{$resource}.update")
                            ->label(__('auth::auth.permissions.update'))
                            ->inline(),

                        Forms\Components\Checkbox::make("permissions.{$resource}.delete")
                            ->label(__('auth::auth.permissions.delete'))
                            ->inline(),

                        Forms\Components\Checkbox::make("permissions.{$resource}.export")
                            ->label(__('auth::auth.permissions.export'))
                            ->inline(),

                        Forms\Components\Checkbox::make("permissions.{$resource}.import")
                            ->label(__('auth::auth.permissions.import'))
                            ->inline(),
                    ])
                    ->columns(6);
            }

            $schema[] = Forms\Components\Section::make($group['label'])
                ->icon($group['icon'])
                ->schema($fieldsets)
                ->collapsible()
                ->collapsed();
        }

        return $schema;
    }

    public static function getGroupedResourcePermissions(): array
    {
        return [
            'patients_booking' => [
                'label' => __('auth::auth.permission_groups.patients_booking'),
                'icon' => 'heroicon-o-user-group',
                'resources' => [
                    'patients' => __('auth::auth.resources.patients'),
                    'appointments' => __('auth::auth.resources.appointments'),
                    'visits' => __('auth::auth.resources.visits'),
                    'waitlist' => __('auth::auth.resources.waitlist'),
                    'treatment_plans' => __('auth::auth.resources.treatment_plans'),
                    'prescriptions' => __('auth::auth.resources.prescriptions'),
                ],
            ],
            'services_packages' => [
                'label' => __('auth::auth.permission_groups.services_packages'),
                'icon' => 'heroicon-o-sparkles',
                'resources' => [
                    'services' => __('auth::auth.resources.services'),
                    'service_categories' => __('auth::auth.resources.service_categories'),
                    'packages' => __('auth::auth.resources.packages'),
                    'consent_templates' => __('auth::auth.resources.consent_templates'),
                    'parameter_templates' => __('auth::auth.resources.parameter_templates'),
                ],
            ],
            'billing_payments' => [
                'label' => __('auth::auth.permission_groups.billing_payments'),
                'icon' => 'heroicon-o-banknotes',
                'resources' => [
                    'invoices' => __('auth::auth.resources.invoices'),
                    'payments' => __('auth::auth.resources.payments'),
                    'tax_rates' => __('auth::auth.resources.tax_rates'),
                ],
            ],
            'inventory_products' => [
                'label' => __('auth::auth.permission_groups.inventory_products'),
                'icon' => 'heroicon-o-cube',
                'resources' => [
                    'products' => __('auth::auth.resources.products'),
                    'product_categories' => __('auth::auth.resources.product_categories'),
                    'suppliers' => __('auth::auth.resources.suppliers'),
                    'purchase_orders' => __('auth::auth.resources.purchase_orders'),
                    'vendor_bills' => __('auth::auth.resources.vendor_bills'),
                    'stock_movements' => __('auth::auth.resources.stock_movements'),
                    'stock_locations' => __('auth::auth.resources.stock_locations'),
                    'stock_transfers' => __('auth::auth.resources.stock_transfers'),
                    'inventory_adjustments' => __('auth::auth.resources.inventory_adjustments'),
                    'uoms' => __('auth::auth.resources.uoms'),
                    'uom_categories' => __('auth::auth.resources.uom_categories'),
                ],
            ],
            'equipment_assets' => [
                'label' => __('auth::auth.permission_groups.equipment_assets'),
                'icon' => 'heroicon-o-wrench-screwdriver',
                'resources' => [
                    'equipment' => __('auth::auth.resources.equipment'),
                    'equipment_parameter_templates' => __('auth::auth.resources.equipment_parameter_templates'),
                    'assets' => __('auth::auth.resources.assets'),
                    'asset_types' => __('auth::auth.resources.asset_types'),
                ],
            ],
            'staff_hr' => [
                'label' => __('auth::auth.permission_groups.staff_hr'),
                'icon' => 'heroicon-o-identification',
                'resources' => [
                    'staff' => __('auth::auth.resources.staff'),
                    'commission_plans' => __('auth::auth.resources.commission_plans'),
                    'payroll_runs' => __('auth::auth.resources.payroll_runs'),
                    'payslips' => __('auth::auth.resources.payslips'),
                    'salary_structures' => __('auth::auth.resources.salary_structures'),
                    'salary_rules' => __('auth::auth.resources.salary_rules'),
                    'salary_rule_categories' => __('auth::auth.resources.salary_rule_categories'),
                ],
            ],
            'attendance_timeoff' => [
                'label' => __('auth::auth.permission_groups.attendance_timeoff'),
                'icon' => 'heroicon-o-clock',
                'resources' => [
                    'attendance' => __('auth::auth.resources.attendance'),
                    'attendance_rules' => __('auth::auth.resources.attendance_rules'),
                    'attendance_violations' => __('auth::auth.resources.attendance_violations'),
                    'time_off_types' => __('auth::auth.resources.time_off_types'),
                    'time_off_allocations' => __('auth::auth.resources.time_off_allocations'),
                    'practitioner_time_off' => __('auth::auth.resources.practitioner_time_off'),
                ],
            ],
            'marketing_loyalty' => [
                'label' => __('auth::auth.permission_groups.marketing_loyalty'),
                'icon' => 'heroicon-o-megaphone',
                'resources' => [
                    'campaigns' => __('auth::auth.resources.campaigns'),
                    'message_templates' => __('auth::auth.resources.message_templates'),
                    'automation_rules' => __('auth::auth.resources.automation_rules'),
                    'notification_logs' => __('auth::auth.resources.notification_logs'),
                    'loyalty_rules' => __('auth::auth.resources.loyalty_rules'),
                    'loyalty_transactions' => __('auth::auth.resources.loyalty_transactions'),
                    'referral_programs' => __('auth::auth.resources.referral_programs'),
                ],
            ],
            'memberships_giftcards' => [
                'label' => __('auth::auth.permission_groups.memberships_giftcards'),
                'icon' => 'heroicon-o-gift',
                'resources' => [
                    'memberships' => __('auth::auth.resources.memberships'),
                    'package_subscriptions' => __('auth::auth.resources.package_subscriptions'),
                    'gift_cards' => __('auth::auth.resources.gift_cards'),
                    'gift_card_templates' => __('auth::auth.resources.gift_card_templates'),
                ],
            ],
            'accounting' => [
                'label' => __('auth::auth.permission_groups.accounting'),
                'icon' => 'heroicon-o-calculator',
                'resources' => [
                    'chart_of_accounts' => __('auth::auth.resources.chart_of_accounts'),
                    'journal_entries' => __('auth::auth.resources.journal_entries'),
                    'journals' => __('auth::auth.resources.journals'),
                    'fiscal_periods' => __('auth::auth.resources.fiscal_periods'),
                    'general_ledger' => __('auth::auth.resources.general_ledger'),
                    'trial_balance' => __('auth::auth.resources.trial_balance'),
                    'balance_sheet' => __('auth::auth.resources.balance_sheet'),
                    'profit_loss' => __('auth::auth.resources.profit_loss'),
                    'cash_flow' => __('auth::auth.resources.cash_flow'),
                    'partner_ledger' => __('auth::auth.resources.partner_ledger'),
                    'cash_management' => __('auth::auth.resources.cash_management'),
                    'default_accounts' => __('auth::auth.resources.default_accounts'),
                ],
            ],
            'operations' => [
                'label' => __('auth::auth.permission_groups.operations'),
                'icon' => 'heroicon-o-calendar-days',
                'resources' => [
                    'calendar' => __('auth::auth.resources.calendar'),
                    'daily_agenda' => __('auth::auth.resources.daily_agenda'),
                    'checkout' => __('auth::auth.resources.checkout'),
                    'room_calendar' => __('auth::auth.resources.room_calendar'),
                    'doctor_dashboard' => __('auth::auth.resources.doctor_dashboard'),
                    'reception_dashboard' => __('auth::auth.resources.reception_dashboard'),
                    'treatment_session' => __('auth::auth.resources.treatment_session'),
                ],
            ],
            'reporting' => [
                'label' => __('auth::auth.permission_groups.reporting'),
                'icon' => 'heroicon-o-chart-bar',
                'resources' => [
                    'financial_reports' => __('auth::auth.resources.financial_reports'),
                    'revenue_reports' => __('auth::auth.resources.revenue_reports'),
                    'patient_reports' => __('auth::auth.resources.patient_reports'),
                    'appointment_reports' => __('auth::auth.resources.appointment_reports'),
                    'inventory_reports' => __('auth::auth.resources.inventory_reports'),
                    'staff_reports' => __('auth::auth.resources.staff_reports'),
                    'campaign_reports' => __('auth::auth.resources.campaign_reports'),
                    'equipment_reports' => __('auth::auth.resources.equipment_reports'),
                    'gift_card_reports' => __('auth::auth.resources.gift_card_reports'),
                    'attendance_reports' => __('auth::auth.resources.attendance_reports'),
                    'treatment_analytics' => __('auth::auth.resources.treatment_analytics'),
                ],
            ],
            'settings_admin' => [
                'label' => __('auth::auth.permission_groups.settings_admin'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'resources' => [
                    'users' => __('auth::auth.resources.users'),
                    'roles' => __('auth::auth.resources.roles'),
                    'access_policies' => __('auth::auth.resources.access_policies'),
                    'branches' => __('auth::auth.resources.branches'),
                    'rooms' => __('auth::auth.resources.rooms'),
                    'work_schedules' => __('auth::auth.resources.work_schedules'),
                    'booking_rules' => __('auth::auth.resources.booking_rules'),
                    'blackout_dates' => __('auth::auth.resources.blackout_dates'),
                    'medicine_catalogs' => __('auth::auth.resources.medicine_catalogs'),
                    'settings' => __('auth::auth.resources.settings'),
                    'general_settings' => __('auth::auth.resources.general_settings'),
                    'usage_dashboard' => __('auth::auth.resources.usage_dashboard'),
                ],
            ],
        ];
    }

    public static function getResourcePermissions(): array
    {
        $resources = [];
        foreach (static::getGroupedResourcePermissions() as $group) {
            $resources = array_merge($resources, $group['resources']);
        }
        return $resources;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('auth::auth.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('auth::auth.fields.display_name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('auth::auth.fields.users_count'))
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('auth::auth.fields.permissions_count'))
                    ->counts('permissions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('level')
                    ->label(__('auth::auth.fields.level'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('auth::auth.fields.system'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('auth::auth.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('auth::auth.fields.active')),

                Tables\Filters\TernaryFilter::make('is_system')
                    ->label(__('auth::auth.fields.system')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn ($record) => $record->is_system || in_array($record->name, ['super-admin', 'super_admin'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Filter out protected roles
                            $protectedRoles = ['super-admin', 'super_admin'];
                            foreach ($records as $record) {
                                if ($record->is_system || in_array($record->name, $protectedRoles)) {
                                    throw new \Exception(__('auth::auth.errors.cannot_delete_system_role'));
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('level');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        $role = Role::find($data['id']);

        if ($role) {
            $permissions = $role->permissions->pluck('name')->toArray();

            foreach (static::getResourcePermissions() as $resource => $label) {
                foreach (['view', 'create', 'update', 'delete', 'export', 'import'] as $action) {
                    $permName = "{$resource}.{$action}";
                    $data['permissions'][$resource][$action] = in_array($permName, $permissions);
                }
            }
        }

        return $data;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['permissions']);
        $data['guard_name'] = 'web';
        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['permissions']);
        return $data;
    }
}
