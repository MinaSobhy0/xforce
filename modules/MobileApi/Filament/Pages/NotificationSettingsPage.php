<?php

namespace Modules\MobileApi\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Core\Models\Setting;
use Modules\MobileApi\Models\PushNotification;

class NotificationSettingsPage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'mobile_api::filament.pages.notification-settings';

    protected static ?string $navigationGroup = 'Mobile App';

    protected static ?int $navigationSort = 20;

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

        // Check notification settings permission
        if ($user->can('mobile_api.manage_notifications')) {
            return true;
        }

        // If permission doesn't exist, allow access (fallback for backwards compatibility)
        $permissionExists = \Spatie\Permission\Models\Permission::where('name', 'mobile_api.manage_notifications')
            ->where('guard_name', 'web')
            ->exists();

        return ! $permissionExists;
    }

    public static function getNavigationLabel(): string
    {
        return __('mobile_api::mobile.notification_settings');
    }

    public function getTitle(): string
    {
        return __('mobile_api::mobile.notification_settings');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('mobile_api::mobile.notification_types'))
                    ->description(__('mobile_api::mobile.notification_types_description'))
                    ->schema($this->getNotificationToggles())
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getNotificationToggles(): array
    {
        $toggles = [];

        foreach (PushNotification::TYPES as $type => $label) {
            $toggles[] = Forms\Components\Toggle::make("notification.{$type}.enabled")
                ->label(__("mobile_api::notifications.types.{$type}"))
                ->helperText(__("mobile_api::notifications.types_descriptions.{$type}"))
                ->default(true)
                ->inline(false);
        }

        return $toggles;
    }

    protected function loadSettings(): array
    {
        $settings = [];

        foreach (PushNotification::TYPES as $type => $label) {
            $key = "notification.{$type}.enabled";
            $value = Setting::getValue($key, null, true);

            // Handle null (not set) as true (enabled by default)
            if ($value === null) {
                $settings[$key] = true;
            } else {
                $settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        return $settings;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::setValue($key, (bool) $value, null, 'notifications');
        }

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
