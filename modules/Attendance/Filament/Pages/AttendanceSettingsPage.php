<?php

namespace Modules\Attendance\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceTypeSetting;
use Modules\Core\Models\Branch;

class AttendanceSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'attendance::filament.pages.attendance-settings';

    public ?array $geofenceData = [];
    public ?array $qrStaticData = [];
    public ?array $qrDynamicData = [];
    public ?array $biometricData = [];

    public ?string $selectedBranchId = null;

    public static function getNavigationLabel(): string
    {
        return __('attendance::attendance.settings.title');
    }

    public function getTitle(): string
    {
        return __('attendance::attendance.settings.title');
    }

    public function mount(): void
    {
        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        $branchId = $this->selectedBranchId;

        foreach (Attendance::CONFIGURABLE_TYPES as $type) {
            $setting = AttendanceTypeSetting::query()
                ->ofType($type)
                ->where(function ($q) use ($branchId) {
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    } else {
                        $q->whereNull('branch_id');
                    }
                })
                ->first();

            $data = [
                'is_enabled' => $setting?->is_enabled ?? false,
                'settings' => $setting?->getMergedSettings() ?? AttendanceTypeSetting::DEFAULT_SETTINGS[$type] ?? [],
            ];

            match ($type) {
                Attendance::TYPE_GEOFENCE => $this->geofenceData = $data,
                Attendance::TYPE_QR_STATIC => $this->qrStaticData = $data,
                Attendance::TYPE_QR_DYNAMIC => $this->qrDynamicData = $data,
                Attendance::TYPE_BIOMETRIC => $this->biometricData = $data,
                default => null,
            };
        }
    }

    public function updatedSelectedBranchId(): void
    {
        $this->loadSettings();
    }

    protected function getForms(): array
    {
        return [
            'branchForm',
            'geofenceForm',
            'qrStaticForm',
            'qrDynamicForm',
            'biometricForm',
        ];
    }

    public function branchForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('selectedBranchId')
                    ->label(__('attendance::attendance.settings.branch'))
                    ->placeholder(__('attendance::attendance.settings.global_settings'))
                    ->options(Branch::query()->pluck('name', 'id'))
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadSettings()),
            ])
            ->statePath('');
    }

    public function geofenceForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('is_enabled')
                    ->label(__('attendance::attendance.settings.enabled'))
                    ->live(),

                Forms\Components\Section::make(__('attendance::attendance.settings.geofence.title'))
                    ->schema([
                        Forms\Components\TextInput::make('settings.radius_meters')
                            ->label(__('attendance::attendance.settings.geofence.radius'))
                            ->numeric()
                            ->default(100)
                            ->suffix('meters')
                            ->helperText(__('attendance::attendance.settings.geofence.radius_help')),

                        Forms\Components\TextInput::make('settings.min_accuracy_meters')
                            ->label(__('attendance::attendance.settings.geofence.min_accuracy'))
                            ->numeric()
                            ->default(50)
                            ->suffix('meters'),

                        Forms\Components\Toggle::make('settings.require_high_accuracy')
                            ->label(__('attendance::attendance.settings.geofence.require_accuracy'))
                            ->default(true),

                        Forms\Components\Toggle::make('settings.allow_mock_location')
                            ->label(__('attendance::attendance.settings.geofence.allow_mock'))
                            ->default(false)
                            ->helperText(__('attendance::attendance.settings.geofence.allow_mock_help')),

                        Forms\Components\Toggle::make('settings.check_on_checkout')
                            ->label(__('attendance::attendance.settings.geofence.check_checkout'))
                            ->default(true),

                        Forms\Components\Repeater::make('settings.locations')
                            ->label(__('attendance::attendance.settings.geofence.locations'))
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('attendance::attendance.settings.geofence.location_name'))
                                    ->required(),

                                Forms\Components\TextInput::make('lat')
                                    ->label(__('attendance::attendance.settings.geofence.latitude'))
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('lng')
                                    ->label(__('attendance::attendance.settings.geofence.longitude'))
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('radius')
                                    ->label(__('attendance::attendance.settings.geofence.location_radius'))
                                    ->numeric()
                                    ->placeholder('Use default')
                                    ->suffix('meters'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel(__('attendance::attendance.settings.geofence.add_location'))
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('is_enabled')),
            ])
            ->statePath('geofenceData');
    }

    public function qrStaticForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('is_enabled')
                    ->label(__('attendance::attendance.settings.enabled'))
                    ->live(),

                Forms\Components\Section::make(__('attendance::attendance.settings.qr_static.title'))
                    ->schema([
                        Forms\Components\Toggle::make('settings.allow_camera_only')
                            ->label(__('attendance::attendance.settings.qr_static.camera_only'))
                            ->default(true)
                            ->helperText(__('attendance::attendance.settings.qr_static.camera_only_help')),

                        Forms\Components\Toggle::make('settings.show_qr_in_app')
                            ->label(__('attendance::attendance.settings.qr_static.show_in_app'))
                            ->default(false)
                            ->helperText(__('attendance::attendance.settings.qr_static.show_in_app_help')),

                        Forms\Components\Toggle::make('settings.require_location')
                            ->label(__('attendance::attendance.settings.qr_static.require_location'))
                            ->default(false),

                        Forms\Components\Placeholder::make('qr_info')
                            ->label(__('attendance::attendance.settings.qr_static.qr_code'))
                            ->content(fn () => __('attendance::attendance.settings.qr_static.qr_generated_info')),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('is_enabled')),
            ])
            ->statePath('qrStaticData');
    }

    public function qrDynamicForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('is_enabled')
                    ->label(__('attendance::attendance.settings.enabled'))
                    ->live(),

                Forms\Components\Section::make(__('attendance::attendance.settings.qr_dynamic.title'))
                    ->schema([
                        Forms\Components\TextInput::make('settings.refresh_interval_seconds')
                            ->label(__('attendance::attendance.settings.qr_dynamic.refresh_interval'))
                            ->numeric()
                            ->default(30)
                            ->suffix('seconds')
                            ->helperText(__('attendance::attendance.settings.qr_dynamic.refresh_interval_help')),

                        Forms\Components\TextInput::make('settings.validity_seconds')
                            ->label(__('attendance::attendance.settings.qr_dynamic.validity'))
                            ->numeric()
                            ->default(60)
                            ->suffix('seconds')
                            ->helperText(__('attendance::attendance.settings.qr_dynamic.validity_help')),

                        Forms\Components\Select::make('settings.algorithm')
                            ->label(__('attendance::attendance.settings.qr_dynamic.algorithm'))
                            ->options([
                                'totp' => 'TOTP (Time-based)',
                                'hotp' => 'HOTP (Counter-based)',
                            ])
                            ->default('totp'),

                        Forms\Components\Toggle::make('settings.display_countdown')
                            ->label(__('attendance::attendance.settings.qr_dynamic.display_countdown'))
                            ->default(true),

                        Forms\Components\Toggle::make('settings.require_location')
                            ->label(__('attendance::attendance.settings.qr_dynamic.require_location'))
                            ->default(false),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('is_enabled')),
            ])
            ->statePath('qrDynamicData');
    }

    public function biometricForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('is_enabled')
                    ->label(__('attendance::attendance.settings.enabled'))
                    ->live(),

                Forms\Components\Section::make(__('attendance::attendance.settings.biometric.title'))
                    ->schema([
                        Forms\Components\Select::make('settings.device_type')
                            ->label(__('attendance::attendance.settings.biometric.device_type'))
                            ->options([
                                'fingerprint' => __('attendance::attendance.settings.biometric.fingerprint'),
                                'face' => __('attendance::attendance.settings.biometric.face'),
                                'iris' => __('attendance::attendance.settings.biometric.iris'),
                            ])
                            ->default('fingerprint'),

                        Forms\Components\Select::make('settings.verification_level')
                            ->label(__('attendance::attendance.settings.biometric.verification_level'))
                            ->options([
                                'low' => __('attendance::attendance.settings.biometric.level_low'),
                                'medium' => __('attendance::attendance.settings.biometric.level_medium'),
                                'high' => __('attendance::attendance.settings.biometric.level_high'),
                            ])
                            ->default('medium')
                            ->helperText(__('attendance::attendance.settings.biometric.verification_level_help')),

                        Forms\Components\Toggle::make('settings.allow_fallback')
                            ->label(__('attendance::attendance.settings.biometric.allow_fallback'))
                            ->default(true)
                            ->live(),

                        Forms\Components\Select::make('settings.fallback_method')
                            ->label(__('attendance::attendance.settings.biometric.fallback_method'))
                            ->options([
                                Attendance::TYPE_QR_STATIC => 'Static QR',
                                Attendance::TYPE_QR_DYNAMIC => 'Dynamic QR',
                                Attendance::TYPE_MANUAL => 'Manual',
                            ])
                            ->default(Attendance::TYPE_QR_STATIC)
                            ->visible(fn (Forms\Get $get) => $get('settings.allow_fallback')),

                        Forms\Components\TextInput::make('settings.api_endpoint')
                            ->label(__('attendance::attendance.settings.biometric.api_endpoint'))
                            ->url()
                            ->placeholder('https://biometric-device.local/api'),

                        Forms\Components\TextInput::make('settings.api_key')
                            ->label(__('attendance::attendance.settings.biometric.api_key'))
                            ->password()
                            ->revealable(),

                        Forms\Components\TagsInput::make('settings.device_ids')
                            ->label(__('attendance::attendance.settings.biometric.device_ids'))
                            ->placeholder(__('attendance::attendance.settings.biometric.device_ids_placeholder'))
                            ->helperText(__('attendance::attendance.settings.biometric.device_ids_help')),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('is_enabled')),
            ])
            ->statePath('biometricData');
    }

    public function saveGeofence(): void
    {
        $this->saveTypeSetting(Attendance::TYPE_GEOFENCE, $this->geofenceData);
    }

    public function saveQrStatic(): void
    {
        $this->saveTypeSetting(Attendance::TYPE_QR_STATIC, $this->qrStaticData);
    }

    public function saveQrDynamic(): void
    {
        $this->saveTypeSetting(Attendance::TYPE_QR_DYNAMIC, $this->qrDynamicData);
    }

    public function saveBiometric(): void
    {
        $this->saveTypeSetting(Attendance::TYPE_BIOMETRIC, $this->biometricData);
    }

    protected function saveTypeSetting(string $type, array $data): void
    {
        try {
            DB::transaction(function () use ($type, $data) {
                $setting = AttendanceTypeSetting::query()
                    ->ofType($type)
                    ->where(function ($q) {
                        if ($this->selectedBranchId) {
                            $q->where('branch_id', $this->selectedBranchId);
                        } else {
                            $q->whereNull('branch_id');
                        }
                    })
                    ->first();

                if (!$setting) {
                    $setting = new AttendanceTypeSetting();
                    $setting->tenant_id = tenant()?->id ?? session('tenant_id');
                    $setting->branch_id = $this->selectedBranchId;
                    $setting->type = $type;
                }

                $setting->is_enabled = $data['is_enabled'] ?? false;
                $setting->settings = $data['settings'] ?? [];

                // Auto-generate secrets for QR types if enabled and not set
                if ($setting->is_enabled) {
                    if ($type === Attendance::TYPE_QR_STATIC && empty($setting->getSetting('qr_secret'))) {
                        $setting->generateStaticQrContent();
                    } elseif ($type === Attendance::TYPE_QR_DYNAMIC && empty($setting->getSetting('secret_key'))) {
                        $setting->generateDynamicQrSecret();
                    }
                }

                $setting->save();
            });

            Notification::make()
                ->title(__('attendance::attendance.settings.saved'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('attendance::attendance.settings.save_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function regenerateQrStatic(): void
    {
        $setting = AttendanceTypeSetting::query()
            ->ofType(Attendance::TYPE_QR_STATIC)
            ->where(function ($q) {
                if ($this->selectedBranchId) {
                    $q->where('branch_id', $this->selectedBranchId);
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->first();

        if ($setting) {
            $setting->generateStaticQrContent();
            Notification::make()
                ->title(__('attendance::attendance.settings.qr_regenerated'))
                ->success()
                ->send();
        }
    }

    public function regenerateQrDynamicSecret(): void
    {
        $setting = AttendanceTypeSetting::query()
            ->ofType(Attendance::TYPE_QR_DYNAMIC)
            ->where(function ($q) {
                if ($this->selectedBranchId) {
                    $q->where('branch_id', $this->selectedBranchId);
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->first();

        if ($setting) {
            $setting->generateDynamicQrSecret();
            Notification::make()
                ->title(__('attendance::attendance.settings.secret_regenerated'))
                ->success()
                ->send();
        }
    }
}
