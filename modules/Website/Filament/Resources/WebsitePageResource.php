<?php

namespace Modules\Website\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Website\Filament\Resources\WebsitePageResource\Pages;
use Modules\Website\Models\WebsitePage;
use Modules\Website\Services\BlockRegistry;

class WebsitePageResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = WebsitePage::class;

    protected static ?string $moduleCode = 'website';

    protected static ?string $permissionKey = 'website';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Marketing';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.website');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getNavigationLabel(): string
    {
        return __('website::website.navigation.pages');
    }

    public static function getModelLabel(): string
    {
        return __('website::website.labels.page');
    }

    public static function getPluralModelLabel(): string
    {
        return __('website::website.labels.pages');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Page')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('website::website.tabs.basic_info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Section::make(__('website::website.sections.page_details'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('title.en')
                                                    ->label(__('website::website.fields.title') . ' (English)')
                                                    ->required()
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('title.ar')
                                                    ->label(__('website::website.fields.title') . ' (Arabic)')
                                                    ->required()
                                                    ->maxLength(255),
                                            ]),

                                        Forms\Components\TextInput::make('slug')
                                            ->label(__('website::website.fields.slug'))
                                            ->required()
                                            ->maxLength(255)
                                            ->rules(['alpha_dash'])
                                            ->unique(ignoreRecord: true)
                                            ->helperText(__('website::website.fields.slug_help')),

                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\Toggle::make('is_homepage')
                                                    ->label(__('website::website.fields.is_homepage'))
                                                    ->helperText(__('website::website.fields.is_homepage_help')),

                                                Forms\Components\Toggle::make('is_published')
                                                    ->label(__('website::website.fields.is_published'))
                                                    ->default(false),

                                                Forms\Components\TextInput::make('sort_order')
                                                    ->label(__('website::website.fields.sort_order'))
                                                    ->numeric()
                                                    ->default(0),
                                            ]),
                                    ]),

                                Forms\Components\Section::make(__('website::website.sections.seo'))
                                    ->description(__('website::website.sections.seo_description'))
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('meta_title.en')
                                                    ->label(__('website::website.fields.meta_title') . ' (English)')
                                                    ->maxLength(70)
                                                    ->helperText(__('website::website.fields.meta_title_help')),

                                                Forms\Components\TextInput::make('meta_title.ar')
                                                    ->label(__('website::website.fields.meta_title') . ' (Arabic)')
                                                    ->maxLength(70),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Textarea::make('meta_description.en')
                                                    ->label(__('website::website.fields.meta_description') . ' (English)')
                                                    ->maxLength(160)
                                                    ->rows(2)
                                                    ->helperText(__('website::website.fields.meta_description_help')),

                                                Forms\Components\Textarea::make('meta_description.ar')
                                                    ->label(__('website::website.fields.meta_description') . ' (Arabic)')
                                                    ->maxLength(160)
                                                    ->rows(2),
                                            ]),
                                    ])
                                    ->collapsed(),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('website::website.tabs.content'))
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Forms\Components\Builder::make('blocks_data')
                                    ->label(__('website::website.fields.blocks'))
                                    ->blocks([
                                        static::getHeroBlock(),
                                        static::getFeaturesBlock(),
                                        static::getServicesBlock(),
                                        static::getTeamBlock(),
                                        static::getTestimonialsBlock(),
                                        static::getContactBlock(),
                                        static::getGalleryBlock(),
                                        static::getCtaBlock(),
                                        static::getTextBlock(),
                                        static::getBeforeAfterBlock(),
                                    ])
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable()
                                    ->blockNumbers(false)
                                    ->addActionLabel(__('website::website.actions.add_block'))
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function getHeroBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('hero')
            ->label(__('website::website.blocks.hero'))
            ->icon('heroicon-o-rectangle-group')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.eyebrow.en')
                        ->label(__('website::website.block_fields.eyebrow') . ' (EN)'),
                    Forms\Components\TextInput::make('content.eyebrow.ar')
                        ->label(__('website::website.block_fields.eyebrow') . ' (AR)'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)')
                        ->required(),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)')
                        ->required(),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('content.subtitle.en')
                        ->label(__('website::website.block_fields.subtitle') . ' (EN)')
                        ->rows(2),
                    Forms\Components\Textarea::make('content.subtitle.ar')
                        ->label(__('website::website.block_fields.subtitle') . ' (AR)')
                        ->rows(2),
                ]),
                Forms\Components\Section::make(__('website::website.sections.buttons'))->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('content.primary_button.label.en')
                            ->label(__('website::website.block_fields.primary_button_label') . ' (EN)'),
                        Forms\Components\TextInput::make('content.primary_button.label.ar')
                            ->label(__('website::website.block_fields.primary_button_label') . ' (AR)'),
                    ]),
                    Forms\Components\TextInput::make('content.primary_button.url')
                        ->label(__('website::website.block_fields.primary_button_url')),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('content.secondary_button.label.en')
                            ->label(__('website::website.block_fields.secondary_button_label') . ' (EN)'),
                        Forms\Components\TextInput::make('content.secondary_button.label.ar')
                            ->label(__('website::website.block_fields.secondary_button_label') . ' (AR)'),
                    ]),
                    Forms\Components\TextInput::make('content.secondary_button.url')
                        ->label(__('website::website.block_fields.secondary_button_url')),
                ])->collapsed(),
                Forms\Components\FileUpload::make('content.background_image')
                    ->label(__('website::website.block_fields.background_image'))
                    ->image()
                    ->disk('tenant')
                    ->directory('website/heroes')
                    ->visibility('private'),
                Forms\Components\Section::make(__('website::website.sections.settings'))->schema([
                    Forms\Components\Select::make('settings.height')
                        ->label(__('website::website.block_fields.height'))
                        ->options([
                            'full' => __('website::website.options.full_screen'),
                            'large' => __('website::website.options.large'),
                            'medium' => __('website::website.options.medium'),
                        ])
                        ->default('full'),
                    Forms\Components\Select::make('settings.text_alignment')
                        ->label(__('website::website.block_fields.text_alignment'))
                        ->options([
                            'left' => __('website::website.options.left'),
                            'center' => __('website::website.options.center'),
                            'right' => __('website::website.options.right'),
                        ])
                        ->default('center'),
                    Forms\Components\TextInput::make('settings.overlay_opacity')
                        ->label(__('website::website.block_fields.overlay_opacity'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.1)
                        ->default(0.5),
                ])->collapsed(),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getFeaturesBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('features')
            ->label(__('website::website.blocks.features'))
            ->icon('heroicon-o-squares-2x2')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)'),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('content.subtitle.en')
                        ->label(__('website::website.block_fields.subtitle') . ' (EN)')
                        ->rows(2),
                    Forms\Components\Textarea::make('content.subtitle.ar')
                        ->label(__('website::website.block_fields.subtitle') . ' (AR)')
                        ->rows(2),
                ]),
                Forms\Components\Repeater::make('content.items')
                    ->label(__('website::website.block_fields.features'))
                    ->schema([
                        Forms\Components\TextInput::make('icon')
                            ->label(__('website::website.block_fields.icon'))
                            ->placeholder('heroicon-o-star'),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('title.en')
                                ->label(__('website::website.block_fields.title') . ' (EN)')
                                ->required(),
                            Forms\Components\TextInput::make('title.ar')
                                ->label(__('website::website.block_fields.title') . ' (AR)'),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('description.en')
                                ->label(__('website::website.block_fields.description') . ' (EN)')
                                ->rows(2),
                            Forms\Components\Textarea::make('description.ar')
                                ->label(__('website::website.block_fields.description') . ' (AR)')
                                ->rows(2),
                        ]),
                    ])
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? null)
                    ->reorderable()
                    ->defaultItems(3),
                Forms\Components\Select::make('settings.columns')
                    ->label(__('website::website.block_fields.columns'))
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(3),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getServicesBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('services')
            ->label(__('website::website.blocks.services'))
            ->icon('heroicon-o-sparkles')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)')
                        ->default('Our Services'),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)')
                        ->default('خدماتنا'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('content.subtitle.en')
                        ->label(__('website::website.block_fields.subtitle') . ' (EN)')
                        ->rows(2),
                    Forms\Components\Textarea::make('content.subtitle.ar')
                        ->label(__('website::website.block_fields.subtitle') . ' (AR)')
                        ->rows(2),
                ]),
                Forms\Components\Toggle::make('content.show_prices')
                    ->label(__('website::website.block_fields.show_prices'))
                    ->default(false),
                Forms\Components\Toggle::make('content.show_booking')
                    ->label(__('website::website.block_fields.show_booking'))
                    ->default(true),
                Forms\Components\TextInput::make('content.limit')
                    ->label(__('website::website.block_fields.limit'))
                    ->numeric()
                    ->default(6),
                Forms\Components\Select::make('settings.layout')
                    ->label(__('website::website.block_fields.layout'))
                    ->options([
                        'grid' => __('website::website.options.grid'),
                        'list' => __('website::website.options.list'),
                    ])
                    ->default('grid'),
                Forms\Components\Select::make('settings.columns')
                    ->label(__('website::website.block_fields.columns'))
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(3),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getTeamBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('team')
            ->label(__('website::website.blocks.team'))
            ->icon('heroicon-o-user-group')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)')
                        ->default('Meet Our Team'),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)')
                        ->default('تعرف على فريقنا'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('content.subtitle.en')
                        ->label(__('website::website.block_fields.subtitle') . ' (EN)')
                        ->rows(2),
                    Forms\Components\Textarea::make('content.subtitle.ar')
                        ->label(__('website::website.block_fields.subtitle') . ' (AR)')
                        ->rows(2),
                ]),
                Forms\Components\Toggle::make('content.show_bio')
                    ->label(__('website::website.block_fields.show_bio'))
                    ->default(true),
                Forms\Components\Select::make('settings.columns')
                    ->label(__('website::website.block_fields.columns'))
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(4),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getTestimonialsBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('testimonials')
            ->label(__('website::website.blocks.testimonials'))
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)')
                        ->default('What Our Clients Say'),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)')
                        ->default('ماذا يقول عملاؤنا'),
                ]),
                Forms\Components\Repeater::make('content.items')
                    ->label(__('website::website.block_fields.testimonials'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('website::website.block_fields.client_name'))
                            ->required(),
                        Forms\Components\FileUpload::make('image')
                            ->label(__('website::website.block_fields.photo'))
                            ->image()
                            ->disk('tenant')
                            ->directory('website/testimonials')
                            ->visibility('private')
                            ->circleCropper(),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('text.en')
                                ->label(__('website::website.block_fields.testimonial') . ' (EN)')
                                ->rows(3)
                                ->required(),
                            Forms\Components\Textarea::make('text.ar')
                                ->label(__('website::website.block_fields.testimonial') . ' (AR)')
                                ->rows(3),
                        ]),
                        Forms\Components\TextInput::make('rating')
                            ->label(__('website::website.block_fields.rating'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->default(5),
                    ])
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->reorderable()
                    ->defaultItems(3),
                Forms\Components\Select::make('settings.layout')
                    ->label(__('website::website.block_fields.layout'))
                    ->options([
                        'carousel' => __('website::website.options.carousel'),
                        'grid' => __('website::website.options.grid'),
                    ])
                    ->default('carousel'),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getContactBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('contact')
            ->label(__('website::website.blocks.contact'))
            ->icon('heroicon-o-envelope')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)')
                        ->default('Contact Us'),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)')
                        ->default('اتصل بنا'),
                ]),
                Forms\Components\Toggle::make('content.show_form')
                    ->label(__('website::website.block_fields.show_form'))
                    ->default(true),
                Forms\Components\Toggle::make('content.show_map')
                    ->label(__('website::website.block_fields.show_map'))
                    ->default(true),
                Forms\Components\Toggle::make('content.show_info')
                    ->label(__('website::website.block_fields.show_info'))
                    ->default(true),
                Forms\Components\Select::make('settings.layout')
                    ->label(__('website::website.block_fields.layout'))
                    ->options([
                        'split' => __('website::website.options.split'),
                        'stacked' => __('website::website.options.stacked'),
                    ])
                    ->default('split'),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getGalleryBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('gallery')
            ->label(__('website::website.blocks.gallery'))
            ->icon('heroicon-o-photo')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)'),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)'),
                ]),
                Forms\Components\FileUpload::make('content.images')
                    ->label(__('website::website.block_fields.images'))
                    ->multiple()
                    ->image()
                    ->disk('tenant')
                    ->directory('website/gallery')
                    ->visibility('private')
                    ->reorderable(),
                Forms\Components\Select::make('settings.columns')
                    ->label(__('website::website.block_fields.columns'))
                    ->options([2 => '2', 3 => '3', 4 => '4'])
                    ->default(3),
                Forms\Components\Toggle::make('settings.lightbox')
                    ->label(__('website::website.block_fields.lightbox'))
                    ->default(true),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getCtaBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('cta')
            ->label(__('website::website.blocks.cta'))
            ->icon('heroicon-o-megaphone')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)')
                        ->required(),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)'),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('content.subtitle.en')
                        ->label(__('website::website.block_fields.subtitle') . ' (EN)')
                        ->rows(2),
                    Forms\Components\Textarea::make('content.subtitle.ar')
                        ->label(__('website::website.block_fields.subtitle') . ' (AR)')
                        ->rows(2),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.button_label.en')
                        ->label(__('website::website.block_fields.button_label') . ' (EN)')
                        ->default('Get Started'),
                    Forms\Components\TextInput::make('content.button_label.ar')
                        ->label(__('website::website.block_fields.button_label') . ' (AR)')
                        ->default('ابدأ الآن'),
                ]),
                Forms\Components\TextInput::make('content.button_url')
                    ->label(__('website::website.block_fields.button_url'))
                    ->default('/book'),
                Forms\Components\Select::make('settings.style')
                    ->label(__('website::website.block_fields.style'))
                    ->options([
                        'gradient' => __('website::website.options.gradient'),
                        'solid' => __('website::website.options.solid'),
                        'outline' => __('website::website.options.outline'),
                    ])
                    ->default('gradient'),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getTextBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('text')
            ->label(__('website::website.blocks.text'))
            ->icon('heroicon-o-document-text')
            ->schema([
                Forms\Components\RichEditor::make('content.content.en')
                    ->label(__('website::website.block_fields.content') . ' (EN)')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'h2', 'h3', 'bulletList', 'orderedList',
                        'link', 'blockquote',
                    ]),
                Forms\Components\RichEditor::make('content.content.ar')
                    ->label(__('website::website.block_fields.content') . ' (AR)')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'h2', 'h3', 'bulletList', 'orderedList',
                        'link', 'blockquote',
                    ]),
                Forms\Components\Select::make('settings.max_width')
                    ->label(__('website::website.block_fields.max_width'))
                    ->options([
                        'prose' => 'Prose (65ch)',
                        'lg' => 'Large',
                        'full' => 'Full Width',
                    ])
                    ->default('prose'),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    protected static function getBeforeAfterBlock(): Forms\Components\Builder\Block
    {
        return Forms\Components\Builder\Block::make('before-after')
            ->label(__('website::website.blocks.before_after'))
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('content.title.en')
                        ->label(__('website::website.block_fields.title') . ' (EN)')
                        ->default('Results'),
                    Forms\Components\TextInput::make('content.title.ar')
                        ->label(__('website::website.block_fields.title') . ' (AR)')
                        ->default('النتائج'),
                ]),
                Forms\Components\Repeater::make('content.items')
                    ->label(__('website::website.block_fields.before_after_items'))
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('title.en')
                                ->label(__('website::website.block_fields.treatment_title') . ' (EN)'),
                            Forms\Components\TextInput::make('title.ar')
                                ->label(__('website::website.block_fields.treatment_title') . ' (AR)'),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\FileUpload::make('before_image')
                                ->label(__('website::website.block_fields.before_image'))
                                ->image()
                                ->disk('tenant')
                                ->directory('website/before-after')
                                ->visibility('private')
                                ->required(),
                            Forms\Components\FileUpload::make('after_image')
                                ->label(__('website::website.block_fields.after_image'))
                                ->image()
                                ->disk('tenant')
                                ->directory('website/before-after')
                                ->visibility('private')
                                ->required(),
                        ]),
                    ])
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? null)
                    ->reorderable()
                    ->defaultItems(1),
                Forms\Components\Select::make('settings.layout')
                    ->label(__('website::website.block_fields.layout'))
                    ->options([
                        'slider' => __('website::website.options.slider'),
                        'grid' => __('website::website.options.grid'),
                    ])
                    ->default('slider'),
                Forms\Components\Toggle::make('is_visible')
                    ->label(__('website::website.fields.visible'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('website::website.fields.slug'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('translated_title')
                    ->label(__('website::website.fields.title'))
                    ->searchable(['title'])
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_homepage')
                    ->label(__('website::website.fields.is_homepage'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label(__('website::website.fields.is_published'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('blocks_count')
                    ->label(__('website::website.fields.blocks_count'))
                    ->counts('blocks'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('website::website.fields.sort_order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('website::website.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label(__('website::website.fields.is_published')),

                Tables\Filters\TernaryFilter::make('is_homepage')
                    ->label(__('website::website.fields.is_homepage')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label(__('website::website.actions.preview'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (WebsitePage $record) => $record->url, true),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsitePages::route('/'),
            'create' => Pages\CreateWebsitePage::route('/create'),
            'edit' => Pages\EditWebsitePage::route('/{record}/edit'),
        ];
    }
}
