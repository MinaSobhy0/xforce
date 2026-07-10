<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlatformSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Platform-wide defaults for MARKETING email sends. Kept intentionally
 * separate from the transactional MAIL_FROM_ADDRESS (noreply@) so a
 * campaign uses a personal / brand-facing address by default while
 * contract signing, password resets, and welcome emails keep their
 * no-reply identity.
 *
 * Per-campaign overrides on the composer take precedence over these
 * defaults; these defaults take precedence over config('mail.from.*').
 */
class PlatformEmailDefaults extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Email Defaults';

    protected static ?string $title = 'Platform Email Defaults';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 25;

    protected static string $view = 'filament.super-admin.pages.platform-email-defaults';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.platform_integrations');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'marketing_from_address' => PlatformSetting::get('platform_email.marketing_from_address'),
            'marketing_from_name' => PlatformSetting::get('platform_email.marketing_from_name'),
            'marketing_reply_to' => PlatformSetting::get('platform_email.marketing_reply_to'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Marketing (broadcasts)')
                ->description('Applied to every platform email campaign unless the campaign itself overrides the field. Kept separate from the transactional noreply@ used by contract signing, password resets, and welcome emails.')
                ->schema([
                    Forms\Components\TextInput::make('marketing_from_address')
                        ->label('From email')
                        ->email()
                        ->maxLength(255)
                        ->placeholder(config('mail.from.address'))
                        ->helperText('e.g. hello@x-linic.com or ibram@x-linic.com. Must be an address on a domain you can DKIM-sign.'),
                    Forms\Components\TextInput::make('marketing_from_name')
                        ->label('From name')
                        ->maxLength(255)
                        ->placeholder(config('mail.from.name'))
                        ->helperText('What recipients see in their inbox next to the address.'),
                    Forms\Components\TextInput::make('marketing_reply_to')
                        ->label('Reply-to')
                        ->email()
                        ->maxLength(255)
                        ->placeholder(config('mail.reply_to.address'))
                        ->helperText('Where a recipient hitting "Reply" actually lands. Point this at a mailbox you check regularly.'),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ([
            'marketing_from_address' => 'platform_email.marketing_from_address',
            'marketing_from_name' => 'platform_email.marketing_from_name',
            'marketing_reply_to' => 'platform_email.marketing_reply_to',
        ] as $formKey => $settingKey) {
            $value = $data[$formKey] ?? null;
            if (filled($value)) {
                PlatformSetting::set($settingKey, $value, 'platform_email');
            } else {
                PlatformSetting::where('key', $settingKey)->delete();
            }
        }

        \Illuminate\Support\Facades\Cache::forget('platform_settings');

        Notification::make()->title('Platform email defaults saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }
}
