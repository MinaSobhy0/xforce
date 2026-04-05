<?php

namespace Modules\Website\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Website\Models\WebsiteSetting;

class WebsiteSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'website::filament.pages.website-settings';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 61;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'admin', 'owner'])) {
            return true;
        }

        return $user->can('website.settings');
    }

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('website::website.navigation.settings');
    }

    public function getTitle(): string
    {
        return __('website::website.page_titles.settings');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('website::website.tabs.branding'))
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\Section::make(__('website::website.sections.logo_favicon'))
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo')
                                            ->label(__('website::website.fields.logo'))
                                            ->image()
                                            ->directory('website/branding')
                                            ->helperText(__('website::website.fields.logo_help')),

                                        Forms\Components\FileUpload::make('favicon')
                                            ->label(__('website::website.fields.favicon'))
                                            ->image()
                                            ->directory('website/branding')
                                            ->helperText(__('website::website.fields.favicon_help')),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make(__('website::website.sections.colors'))
                                    ->schema([
                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label(__('website::website.fields.primary_color'))
                                            ->default('#3B82F6'),

                                        Forms\Components\ColorPicker::make('secondary_color')
                                            ->label(__('website::website.fields.secondary_color'))
                                            ->default('#10B981'),

                                        Forms\Components\ColorPicker::make('accent_color')
                                            ->label(__('website::website.fields.accent_color'))
                                            ->default('#F59E0B'),
                                    ])
                                    ->columns(3),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('website::website.tabs.contact'))
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Forms\Components\Section::make(__('website::website.sections.contact_info'))
                                    ->schema([
                                        Forms\Components\TextInput::make('contact_email')
                                            ->label(__('website::website.fields.contact_email'))
                                            ->email(),

                                        Forms\Components\TextInput::make('contact_phone')
                                            ->label(__('website::website.fields.contact_phone'))
                                            ->tel(),

                                        Forms\Components\Textarea::make('contact_address')
                                            ->label(__('website::website.fields.contact_address'))
                                            ->rows(2),

                                        Forms\Components\Textarea::make('google_maps_embed')
                                            ->label(__('website::website.fields.google_maps_embed'))
                                            ->rows(3)
                                            ->helperText(__('website::website.fields.google_maps_embed_help')),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('website::website.tabs.social'))
                            ->icon('heroicon-o-share')
                            ->schema([
                                Forms\Components\Section::make(__('website::website.sections.social_links'))
                                    ->schema([
                                        Forms\Components\TextInput::make('social_facebook')
                                            ->label('Facebook')
                                            ->url()
                                            ->prefix('https://'),

                                        Forms\Components\TextInput::make('social_instagram')
                                            ->label('Instagram')
                                            ->url()
                                            ->prefix('https://'),

                                        Forms\Components\TextInput::make('social_twitter')
                                            ->label('Twitter / X')
                                            ->url()
                                            ->prefix('https://'),

                                        Forms\Components\TextInput::make('social_linkedin')
                                            ->label('LinkedIn')
                                            ->url()
                                            ->prefix('https://'),

                                        Forms\Components\TextInput::make('social_youtube')
                                            ->label('YouTube')
                                            ->url()
                                            ->prefix('https://'),

                                        Forms\Components\TextInput::make('social_tiktok')
                                            ->label('TikTok')
                                            ->url()
                                            ->prefix('https://'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('website::website.tabs.footer'))
                            ->icon('heroicon-o-bars-3')
                            ->schema([
                                Forms\Components\Section::make(__('website::website.sections.footer'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\Textarea::make('footer_text.en')
                                                ->label(__('website::website.fields.footer_text') . ' (EN)')
                                                ->rows(2),

                                            Forms\Components\Textarea::make('footer_text.ar')
                                                ->label(__('website::website.fields.footer_text') . ' (AR)')
                                                ->rows(2),
                                        ]),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('website::website.tabs.advanced'))
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Forms\Components\Section::make(__('website::website.sections.custom_code'))
                                    ->description(__('website::website.sections.custom_code_description'))
                                    ->schema([
                                        Forms\Components\Textarea::make('custom_css')
                                            ->label(__('website::website.fields.custom_css'))
                                            ->rows(6)
                                            ->helperText(__('website::website.fields.custom_css_help')),

                                        Forms\Components\Textarea::make('custom_head')
                                            ->label(__('website::website.fields.custom_head'))
                                            ->rows(4)
                                            ->helperText(__('website::website.fields.custom_head_help')),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            WebsiteSetting::set($key, $value);
        }

        Notification::make()
            ->title(__('website::website.messages.settings_saved'))
            ->success()
            ->send();
    }

    protected function loadSettings(): array
    {
        return WebsiteSetting::getAllForTenant();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label(__('website::website.actions.save_settings'))
                ->submit('save'),
        ];
    }
}
