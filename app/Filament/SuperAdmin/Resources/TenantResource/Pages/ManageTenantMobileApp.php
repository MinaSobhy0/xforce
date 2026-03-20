<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use Modules\Core\Models\Tenant;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Storage;

class ManageTenantMobileApp extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TenantResource::class;

    protected static string $view = 'filament.super-admin.pages.manage-tenant-mobile-app';

    protected static ?string $title = 'Mobile App Configuration';

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    public ?array $data = [];

    public Tenant $tenant;

    public function mount(int|string $record): void
    {
        $this->tenant = Tenant::findOrFail($record);

        $this->form->fill($this->tenant->getMobileAppConfig());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Mobile App Configuration')
                    ->columnSpanFull()
                    ->tabs([
                        // Tab 1: Branding
                        Forms\Components\Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\Section::make('App Identity')
                                    ->description('Customize how the mobile app appears to staff')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('branding.app_name')
                                            ->label('App Name')
                                            ->placeholder($this->tenant->name)
                                            ->helperText('Leave empty to use clinic name'),

                                        Forms\Components\FileUpload::make('branding.logo_url')
                                            ->label('App Logo')
                                            ->image()
                                            ->directory('mobile-logos')
                                            ->visibility('public')
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('512')
                                            ->imageResizeTargetHeight('512')
                                            ->maxSize(1024)
                                            ->helperText('Square image, max 1MB'),
                                    ]),

                                Forms\Components\Section::make('Colors')
                                    ->description('Define the color scheme for the mobile app')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\ColorPicker::make('branding.primary_color')
                                            ->label('Primary Color')
                                            ->helperText('Main brand color (buttons, headers)'),

                                        Forms\Components\ColorPicker::make('branding.secondary_color')
                                            ->label('Secondary Color')
                                            ->helperText('Supporting brand color'),

                                        Forms\Components\ColorPicker::make('branding.accent_color')
                                            ->label('Accent Color')
                                            ->helperText('Highlights and notifications'),
                                    ]),

                                Forms\Components\Section::make('Display Options')
                                    ->schema([
                                        Forms\Components\Toggle::make('branding.dark_mode_enabled')
                                            ->label('Dark Mode Available')
                                            ->helperText('Allow users to switch to dark mode'),
                                    ]),
                            ]),

                        // Tab 2: Navigation
                        Forms\Components\Tabs\Tab::make('Navigation')
                            ->icon('heroicon-o-bars-3')
                            ->schema([
                                Forms\Components\Section::make('Bottom Tab Bar')
                                    ->description('Configure main navigation tabs (max 5). Drag to reorder.')
                                    ->schema([
                                        Forms\Components\Repeater::make('navigation.tabs')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\Select::make('id')
                                                    ->label('Screen')
                                                    ->options(Tenant::getAvailableNavigationScreens())
                                                    ->required()
                                                    ->distinct(),

                                                Forms\Components\Toggle::make('enabled')
                                                    ->label('Enabled')
                                                    ->default(true)
                                                    ->inline(false),

                                                Forms\Components\Hidden::make('sort'),
                                            ])
                                            ->columns(2)
                                            ->reorderable()
                                            ->reorderableWithButtons()
                                            ->maxItems(5)
                                            ->minItems(1)
                                            ->defaultItems(5)
                                            ->itemLabel(fn (array $state): ?string =>
                                                Tenant::getAvailableNavigationScreens()[$state['id'] ?? ''] ?? 'Select screen'
                                            )
                                            ->collapsible()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                // Auto-update sort values based on order
                                                if (is_array($state)) {
                                                    $updated = [];
                                                    $sort = 1;
                                                    foreach ($state as $key => $item) {
                                                        $item['sort'] = $sort++;
                                                        $updated[$key] = $item;
                                                    }
                                                    $set('navigation.tabs', $updated);
                                                }
                                            }),
                                    ]),

                                Forms\Components\Section::make('More Menu Items')
                                    ->description('Configure items in the "More" menu. Drag to reorder.')
                                    ->schema([
                                        Forms\Components\Repeater::make('navigation.more_menu')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\Select::make('id')
                                                    ->label('Screen')
                                                    ->options(Tenant::getAvailableMoreMenuScreens())
                                                    ->required()
                                                    ->distinct(),

                                                Forms\Components\Toggle::make('enabled')
                                                    ->label('Enabled')
                                                    ->default(true)
                                                    ->inline(false),

                                                Forms\Components\Hidden::make('sort'),
                                            ])
                                            ->columns(2)
                                            ->reorderable()
                                            ->reorderableWithButtons()
                                            ->itemLabel(fn (array $state): ?string =>
                                                Tenant::getAvailableMoreMenuScreens()[$state['id'] ?? ''] ?? 'Select screen'
                                            )
                                            ->collapsible()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                // Auto-update sort values based on order
                                                if (is_array($state)) {
                                                    $updated = [];
                                                    $sort = 1;
                                                    foreach ($state as $key => $item) {
                                                        $item['sort'] = $sort++;
                                                        $updated[$key] = $item;
                                                    }
                                                    $set('navigation.more_menu', $updated);
                                                }
                                            }),
                                    ]),
                            ]),

                        // Tab 3: Screen Layouts
                        Forms\Components\Tabs\Tab::make('Screen Layouts')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Forms\Components\Section::make('Dashboard Components')
                                    ->description('Configure which components appear on the dashboard and their order')
                                    ->schema([
                                        Forms\Components\Repeater::make('screens.dashboard.components')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\Select::make('type')
                                                    ->label('Component')
                                                    ->options(Tenant::getAvailableDashboardComponents())
                                                    ->required()
                                                    ->distinct(),

                                                Forms\Components\Toggle::make('enabled')
                                                    ->label('Enabled')
                                                    ->default(true)
                                                    ->inline(false),

                                                Forms\Components\Hidden::make('sort'),

                                                Forms\Components\KeyValue::make('props')
                                                    ->label('Custom Properties')
                                                    ->helperText('Optional component-specific settings')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->reorderable()
                                            ->reorderableWithButtons()
                                            ->itemLabel(fn (array $state): ?string =>
                                                Tenant::getAvailableDashboardComponents()[$state['type'] ?? ''] ?? 'Select component'
                                            )
                                            ->collapsible()
                                            ->collapsed()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                // Auto-update sort values based on order
                                                if (is_array($state)) {
                                                    $updated = [];
                                                    $sort = 1;
                                                    foreach ($state as $key => $item) {
                                                        $item['sort'] = $sort++;
                                                        $updated[$key] = $item;
                                                    }
                                                    $set('screens.dashboard.components', $updated);
                                                }
                                            }),
                                    ]),
                            ]),

                        // Tab 4: Features
                        Forms\Components\Tabs\Tab::make('Features')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make('Attendance Features')
                                    ->description('Configure attendance tracking options')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('features.attendance_photo_required')
                                            ->label('Photo Required for Check-in')
                                            ->helperText('Require selfie when checking in/out'),

                                        Forms\Components\Toggle::make('features.break_tracking')
                                            ->label('Break Tracking')
                                            ->helperText('Allow staff to track breaks'),

                                        Forms\Components\Toggle::make('features.geofence_check_in')
                                            ->label('Geofence Check-in')
                                            ->helperText('Verify location when checking in'),

                                        Forms\Components\Toggle::make('features.qr_check_in')
                                            ->label('QR Code Check-in')
                                            ->helperText('Allow check-in via QR code scanning'),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Configuration')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action(fn () => $this->save()),

            Actions\Action::make('reset')
                ->label('Reset to Defaults')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset Mobile App Configuration')
                ->modalDescription('This will reset all mobile app settings to their default values. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, reset to defaults')
                ->action(function () {
                    $this->tenant->mobile_config = null;
                    $this->tenant->save();

                    $this->form->fill($this->tenant->getMobileAppConfig());

                    Notification::make()
                        ->title('Configuration reset to defaults')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('back')
                ->label('Back to Clinic')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => TenantResource::getUrl('view', ['record' => $this->tenant])),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            // Update sort values based on array order
            if (isset($data['navigation']['tabs'])) {
                $data['navigation']['tabs'] = $this->updateSortValues($data['navigation']['tabs']);
            }

            if (isset($data['navigation']['more_menu'])) {
                $data['navigation']['more_menu'] = $this->updateSortValues($data['navigation']['more_menu']);
            }

            if (isset($data['screens']['dashboard']['components'])) {
                $data['screens']['dashboard']['components'] = $this->updateSortValues($data['screens']['dashboard']['components']);
            }

            $this->tenant->setMobileAppConfig($data);

            Notification::make()
                ->title('Mobile app configuration saved')
                ->success()
                ->send();

        } catch (Halt $exception) {
            return;
        }
    }

    /**
     * Update sort values based on array order.
     */
    protected function updateSortValues(array $items): array
    {
        $sorted = [];
        $index = 1;

        foreach ($items as $item) {
            $item['sort'] = $index++;
            $sorted[] = $item;
        }

        return $sorted;
    }

    public function getBreadcrumbs(): array
    {
        return [
            TenantResource::getUrl() => 'Clinics',
            TenantResource::getUrl('view', ['record' => $this->tenant]) => $this->tenant->name,
            '' => 'Mobile App',
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Mobile App';
    }

    public function getTitle(): string
    {
        return "Mobile App - {$this->tenant->name}";
    }
}
