<?php

namespace Modules\Website\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Website\Models\WebsiteMenu;
use Modules\Website\Models\WebsitePage;

class MenuEditor extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static string $view = 'website::filament.pages.menu-editor';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 62;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'super_admin', 'admin', 'owner'])) {
            return true;
        }

        return $user->can('website.menus');
    }

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('website::website.navigation.menus');
    }

    public function getTitle(): string
    {
        return __('website::website.page_titles.menus');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadMenus());
    }

    public function form(Form $form): Form
    {
        $pageOptions = WebsitePage::published()
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($page) => [$page->url => $page->translated_title])
            ->toArray();

        return $form
            ->schema([
                Forms\Components\Tabs::make('Menus')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('website::website.menu_locations.header'))
                            ->icon('heroicon-o-bars-3')
                            ->schema([
                                Forms\Components\Repeater::make('header')
                                    ->label(__('website::website.fields.menu_items'))
                                    ->schema(static::getMenuItemSchema($pageOptions))
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? 'Menu Item')
                                    ->reorderable()
                                    ->defaultItems(0)
                                    ->addActionLabel(__('website::website.actions.add_menu_item')),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('website::website.menu_locations.footer'))
                            ->icon('heroicon-o-bars-3-bottom-left')
                            ->schema([
                                Forms\Components\Repeater::make('footer')
                                    ->label(__('website::website.fields.menu_items'))
                                    ->schema(static::getMenuItemSchema($pageOptions))
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? 'Menu Item')
                                    ->reorderable()
                                    ->defaultItems(0)
                                    ->addActionLabel(__('website::website.actions.add_menu_item')),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected static function getMenuItemSchema(array $pageOptions): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('label.en')
                    ->label(__('website::website.fields.menu_label') . ' (EN)')
                    ->required(),
                Forms\Components\TextInput::make('label.ar')
                    ->label(__('website::website.fields.menu_label') . ' (AR)'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('url')
                    ->label(__('website::website.fields.menu_url'))
                    ->options(array_merge(
                        [
                            '/' => __('website::website.options.homepage'),
                            '#' => __('website::website.options.none'),
                        ],
                        $pageOptions,
                        [
                            '/book' => __('website::website.options.booking_page'),
                            '/contact' => __('website::website.options.contact_page'),
                        ]
                    ))
                    ->searchable()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('custom_url')
                            ->label(__('website::website.fields.custom_url'))
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => $data['custom_url'])
                    ->required(),

                Forms\Components\Select::make('target')
                    ->label(__('website::website.fields.menu_target'))
                    ->options([
                        '_self' => __('website::website.options.same_window'),
                        '_blank' => __('website::website.options.new_window'),
                    ])
                    ->default('_self'),
            ]),
            Forms\Components\TextInput::make('icon')
                ->label(__('website::website.fields.menu_icon'))
                ->placeholder('heroicon-o-home'),
            Forms\Components\Repeater::make('children')
                ->label(__('website::website.fields.submenu'))
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('label.en')
                            ->label(__('website::website.fields.menu_label') . ' (EN)')
                            ->required(),
                        Forms\Components\TextInput::make('label.ar')
                            ->label(__('website::website.fields.menu_label') . ' (AR)'),
                    ]),
                    Forms\Components\TextInput::make('url')
                        ->label(__('website::website.fields.menu_url'))
                        ->required(),
                ])
                ->collapsed()
                ->defaultItems(0)
                ->addActionLabel(__('website::website.actions.add_submenu_item')),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        WebsiteMenu::setItems('header', $data['header'] ?? []);
        WebsiteMenu::setItems('footer', $data['footer'] ?? []);

        Notification::make()
            ->title(__('website::website.messages.menus_saved'))
            ->success()
            ->send();
    }

    protected function loadMenus(): array
    {
        return [
            'header' => WebsiteMenu::getItems('header'),
            'footer' => WebsiteMenu::getItems('footer'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label(__('website::website.actions.save_menus'))
                ->submit('save'),
        ];
    }
}
