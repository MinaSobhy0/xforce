<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformWhatsAppTemplateResource\Pages;
use App\Models\PlatformWhatsAppTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Marketing\Models\MessageTemplate;

/**
 * Platform-managed WhatsApp template catalog (SuperAdmin only).
 *
 * Platform admins author master templates here once. Tenants then
 * "adopt" via the tenant-side PlatformTemplateCatalog page — adoption
 * clones the template into their tenant message_templates and submits
 * to Meta for per-WABA approval.
 *
 * Mirrors EmailTemplateResource.php for form layout + Translatable
 * locale switcher.
 */
class PlatformWhatsAppTemplateResource extends Resource
{
    use Translatable;

    protected static ?string $model = PlatformWhatsAppTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationLabel = 'WhatsApp Catalog';

    protected static ?string $navigationGroup = 'Apps & Modules';

    protected static ?int $navigationSort = 35;

    // Override Filament's auto-slug (`platform-whats-app-templates`) — same
    // CamelCase split issue that hit WhatsAppConversationResource.
    protected static ?string $slug = 'platform-whatsapp-templates';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->helperText('Becomes Meta\'s template name (lowercase, underscores). Cannot change after tenants adopt.'),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Display label admins see in the catalog (translatable).'),

                    Forms\Components\Select::make('category')
                        ->required()
                        ->options(PlatformWhatsAppTemplate::categories())
                        ->helperText('UTILITY for transactional (reminders, OTP). MARKETING for promos. AUTHENTICATION for codes.'),

                    Forms\Components\TextInput::make('default_language')
                        ->required()
                        ->default('en_US')
                        ->maxLength(16)
                        ->helperText('Meta language code, e.g. en_US, ar, fr.'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                ]),

            Forms\Components\Section::make('Content')
                ->description('Use the locale switcher in the header to author each language. Use {{variable_name}} for placeholders.')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('Admin notes / when to use')
                        ->rows(2),

                    Forms\Components\Textarea::make('body')
                        ->label('Body')
                        ->required()
                        ->rows(4)
                        ->maxLength(1024)
                        ->helperText('e.g. "Hi {{patient_name}}, your appointment on {{date}} at {{time}} is confirmed." Up to 1024 chars.'),

                    Forms\Components\Textarea::make('footer')
                        ->label('Footer (optional)')
                        ->rows(1)
                        ->maxLength(60),
                ]),

            Forms\Components\Section::make('Header (optional)')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('header_type')
                        ->options([
                            'none' => 'None',
                            'text' => 'Text',
                            'image' => 'Image',
                            'document' => 'Document',
                        ])
                        ->default('none')
                        ->live(),

                    Forms\Components\KeyValue::make('header_content')
                        ->label('Header content')
                        ->keyLabel('Field')
                        ->valueLabel('Value')
                        ->helperText('For text: {en: "Title", ar: "العنوان"}. For media: {url: "https://...", filename: "doc.pdf"}.')
                        ->visible(fn (Forms\Get $get) => in_array($get('header_type'), ['text', 'image', 'document'], true)),
                ]),

            Forms\Components\Section::make('Buttons (max 3 quick-reply)')
                ->schema([
                    Forms\Components\Repeater::make('buttons_json')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->required()
                                ->maxLength(25)
                                ->helperText('Button text shown to the recipient (Meta limit: 25 chars).'),

                            Forms\Components\Select::make('action')
                                ->options([
                                    'confirm_appointment' => 'Confirm appointment',
                                    'reschedule_appointment' => 'Reschedule appointment',
                                    'cancel_appointment' => 'Cancel appointment',
                                    'custom' => 'Custom',
                                ])
                                ->default('custom')
                                ->live(),

                            Forms\Components\TextInput::make('custom_payload')
                                ->visible(fn (Forms\Get $get) => $get('action') === 'custom')
                                ->helperText('Free-form ID returned in the inbound webhook when the user taps this button.'),
                        ])
                        ->maxItems(3)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['label'] ?? 'New button'),
                ]),

            Forms\Components\Section::make('Variable examples (for Meta review)')
                ->description('Meta requires example values for each {{var}} in the body so reviewers can preview the rendered message. Keys must match your placeholders.')
                ->collapsed()
                ->schema([
                    Forms\Components\KeyValue::make('variables_json')
                        ->label('')
                        ->keyLabel('Variable name')
                        ->valueLabel('Example value')
                        ->keyPlaceholder('patient_name')
                        ->valuePlaceholder('John Doe'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        MessageTemplate::META_CATEGORY_UTILITY => 'info',
                        MessageTemplate::META_CATEGORY_MARKETING => 'warning',
                        MessageTemplate::META_CATEGORY_AUTHENTICATION => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('default_language')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last modified')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(PlatformWhatsAppTemplate::categories()),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformWhatsAppTemplates::route('/'),
            'create' => Pages\CreatePlatformWhatsAppTemplate::route('/create'),
            'edit' => Pages\EditPlatformWhatsAppTemplate::route('/{record}/edit'),
        ];
    }
}
