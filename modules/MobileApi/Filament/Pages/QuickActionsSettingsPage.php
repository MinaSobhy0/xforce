<?php

namespace Modules\MobileApi\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Core\Models\Tenant;

class QuickActionsSettingsPage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string $view = 'mobile_api::filament.pages.quick-actions-settings';

    protected static ?string $navigationGroup = 'Mobile App';

    protected static ?int $navigationSort = 15;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super admin and key roles have full access
        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'tenant-owner', 'tenant_owner', 'owner', 'admin'])) {
            return true;
        }

        // Check permission
        if ($user->can('mobile_api.manage_quick_actions')) {
            return true;
        }

        // If permission doesn't exist, allow access (fallback for backwards compatibility)
        $permissionExists = \Spatie\Permission\Models\Permission::where('name', 'mobile_api.manage_quick_actions')
            ->where('guard_name', 'web')
            ->exists();

        return ! $permissionExists;
    }

    public static function getNavigationLabel(): string
    {
        return __('mobile_api::mobile.quick_actions_settings');
    }

    public function getTitle(): string
    {
        return __('mobile_api::mobile.quick_actions_settings');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('mobile_api::mobile.quick_actions_section'))
                    ->description(__('mobile_api::mobile.quick_actions_description'))
                    ->schema([
                        Forms\Components\Repeater::make('quick_actions')
                            ->label('')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\Select::make('id')
                                            ->label(__('mobile_api::mobile.quick_action_screen'))
                                            ->options(Tenant::getAvailableQuickActions())
                                            ->required()
                                            ->searchable(),

                                        Forms\Components\Select::make('icon')
                                            ->label(__('mobile_api::mobile.quick_action_icon'))
                                            ->options(Tenant::getAvailableIcons())
                                            ->required()
                                            ->searchable(),

                                        Forms\Components\ColorPicker::make('icon_color')
                                            ->label(__('mobile_api::mobile.quick_action_color'))
                                            ->required(),

                                        Forms\Components\Toggle::make('enabled')
                                            ->label(__('mobile_api::mobile.quick_action_enabled'))
                                            ->default(true)
                                            ->inline(false),
                                    ]),
                            ])
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['id'])
                                    ? (Tenant::getAvailableQuickActions()[$state['id']] ?? $state['id'])
                                    : null
                            )
                            ->defaultItems(0)
                            ->addActionLabel(__('mobile_api::mobile.add_quick_action')),
                    ]),
            ])
            ->statePath('data');
    }

    protected function loadSettings(): array
    {
        $tenant = app('currentTenant');
        $config = $tenant?->getMobileAppConfig() ?? Tenant::getDefaultMobileConfig();

        $quickActions = $config['quick_actions'] ?? [];

        // Add sort index based on array position
        $quickActions = collect($quickActions)
            ->sortBy('sort')
            ->values()
            ->map(function ($action, $index) {
                $action['sort'] = $index + 1;

                return $action;
            })
            ->all();

        return [
            'quick_actions' => $quickActions,
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $tenant = app('currentTenant');
        if (! $tenant) {
            Notification::make()
                ->title(__('mobile_api::mobile.error_no_tenant'))
                ->danger()
                ->send();

            return;
        }

        // Get current config and update quick_actions
        $config = $tenant->getMobileAppConfig();

        // Add sort order based on array position
        $quickActions = collect($data['quick_actions'] ?? [])
            ->values()
            ->map(function ($action, $index) {
                $action['sort'] = $index + 1;

                return $action;
            })
            ->all();

        $config['quick_actions'] = $quickActions;

        // Save to tenant
        $tenant->mobile_config = $config;
        $tenant->save();

        Notification::make()
            ->title(__('mobile_api::mobile.settings_saved'))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label(__('mobile_api::mobile.save_settings'))
                ->submit('save'),
        ];
    }
}
