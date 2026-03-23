<?php

namespace Modules\MobileApi\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Setting;
use Modules\MobileApi\Models\DeviceToken;
use Modules\MobileApi\Models\PushNotification;

class NotificationSettingsPage extends Page implements Forms\Contracts\HasForms, Tables\Contracts\HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

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
                // Device Restriction Section
                Forms\Components\Section::make(__('mobile_api::mobile.device_restriction'))
                    ->description(__('mobile_api::mobile.device_restriction_description'))
                    ->schema([
                        Forms\Components\Toggle::make('mobile.single_device_mode')
                            ->label(__('mobile_api::mobile.single_device_mode'))
                            ->helperText(__('mobile_api::mobile.single_device_mode_help'))
                            ->default(false)
                            ->inline(false),
                    ])
                    ->columns(1),

                // Notification Types Section
                Forms\Components\Section::make(__('mobile_api::mobile.notification_types'))
                    ->description(__('mobile_api::mobile.notification_types_description'))
                    ->schema($this->getNotificationToggles())
                    ->columns(1),
            ])
            ->statePath('data');
    }

    /**
     * Table for managing registered devices.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(DeviceToken::query()->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('mobile_api::mobile.device_user'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('device_id')
                    ->label(__('mobile_api::mobile.device_id'))
                    ->searchable()
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->device_id),
                Tables\Columns\TextColumn::make('platform')
                    ->label(__('mobile_api::mobile.device_platform'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ios' => 'info',
                        'android' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                Tables\Columns\TextColumn::make('app_version')
                    ->label(__('mobile_api::mobile.device_app_version'))
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('mobile_api::mobile.device_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('mobile_api::mobile.device_last_used'))
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('mobile_api::mobile.device_registered'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'ios' => 'iOS',
                        'android' => 'Android',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('mobile_api::mobile.device_active'))
                    ->trueLabel(__('mobile_api::mobile.active_only'))
                    ->falseLabel(__('mobile_api::mobile.inactive_only')),
            ])
            ->actions([
                Tables\Actions\Action::make('deactivate')
                    ->label(__('mobile_api::mobile.deactivate_device'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('mobile_api::mobile.deactivate_device'))
                    ->modalDescription(__('mobile_api::mobile.deactivate_device_confirm'))
                    ->visible(fn (DeviceToken $record) => $record->is_active)
                    ->action(function (DeviceToken $record) {
                        $record->deactivate();
                        Notification::make()
                            ->title(__('mobile_api::mobile.device_deactivated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('activate')
                    ->label(__('mobile_api::mobile.activate_device'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DeviceToken $record) => ! $record->is_active)
                    ->action(function (DeviceToken $record) {
                        // If single device mode is enabled, deactivate other devices for this user
                        if ($this->isSingleDeviceModeEnabled()) {
                            DeviceToken::where('user_id', $record->user_id)
                                ->where('id', '!=', $record->id)
                                ->update(['is_active' => false]);
                        }
                        $record->activate();
                        Notification::make()
                            ->title(__('mobile_api::mobile.device_activated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deactivate_selected')
                    ->label(__('mobile_api::mobile.deactivate_selected'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $records->each->deactivate();
                        Notification::make()
                            ->title(__('mobile_api::mobile.devices_deactivated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('last_used_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * Check if single device mode is enabled.
     */
    public function isSingleDeviceModeEnabled(): bool
    {
        $value = Setting::getValue('mobile.single_device_mode', null, false);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
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
        $settings = [
            'mobile' => [
                'single_device_mode' => $this->isSingleDeviceModeEnabled(),
            ],
            'notification' => [],
        ];

        foreach (PushNotification::TYPES as $type => $label) {
            $key = "notification.{$type}.enabled";
            $value = Setting::getValue($key, null, true);

            // Handle null (not set) as true (enabled by default)
            if ($value === null) {
                $enabled = true;
            } else {
                $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }

            $settings['notification'][$type] = ['enabled' => $enabled];
        }

        return $settings;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save single device mode setting
        if (isset($data['mobile']['single_device_mode'])) {
            Setting::setValue('mobile.single_device_mode', (bool) $data['mobile']['single_device_mode'], null, 'mobile');
        }

        // Data is nested: ['notification' => ['type' => ['enabled' => bool]]]
        // We need to flatten it to: 'notification.type.enabled' => bool
        if (isset($data['notification']) && is_array($data['notification'])) {
            foreach ($data['notification'] as $type => $settings) {
                if (isset($settings['enabled'])) {
                    $key = "notification.{$type}.enabled";
                    Setting::setValue($key, (bool) $settings['enabled'], null, 'notifications');
                }
            }
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
