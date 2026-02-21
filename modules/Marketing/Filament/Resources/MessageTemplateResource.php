<?php

namespace Modules\Marketing\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Marketing\Filament\Resources\MessageTemplateResource\Pages;
use Modules\Marketing\Models\MessageTemplate;

class MessageTemplateResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = MessageTemplate::class;

    protected static ?string $moduleCode = 'marketing';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('marketing::marketing.navigation.templates');
    }

    public static function getModelLabel(): string
    {
        return __('marketing::marketing.labels.template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketing::marketing.labels.templates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('marketing::marketing.sections.template_details'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label(__('marketing::marketing.fields.code'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('channel')
                                    ->label(__('marketing::marketing.fields.channel'))
                                    ->options(MessageTemplate::channels())
                                    ->required()
                                    ->live(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('marketing::marketing.fields.name_en'))
                                    ->required(),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('marketing::marketing.fields.name_ar'))
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('type')
                            ->label(__('marketing::marketing.fields.type'))
                            ->options(MessageTemplate::types())
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('marketing::marketing.sections.content'))
                    ->schema([
                        // Subject for email only
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('subject.en')
                                    ->label(__('marketing::marketing.fields.subject_en'))
                                    ->visible(fn (Forms\Get $get) => $get('channel') === 'email'),

                                Forms\Components\TextInput::make('subject.ar')
                                    ->label(__('marketing::marketing.fields.subject_ar'))
                                    ->visible(fn (Forms\Get $get) => $get('channel') === 'email'),
                            ]),

                        // Content
                        Forms\Components\Tabs::make('content_tabs')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\RichEditor::make('content.en')
                                            ->label(__('marketing::marketing.fields.content'))
                                            ->visible(fn (Forms\Get $get) => $get('channel') === 'email')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList',
                                            ]),

                                        Forms\Components\Textarea::make('content.en')
                                            ->label(__('marketing::marketing.fields.content'))
                                            ->visible(fn (Forms\Get $get) => $get('channel') !== 'email')
                                            ->rows(5)
                                            ->helperText(__('marketing::marketing.helpers.variables')),
                                    ]),

                                Forms\Components\Tabs\Tab::make('Arabic')
                                    ->schema([
                                        Forms\Components\RichEditor::make('content.ar')
                                            ->label(__('marketing::marketing.fields.content'))
                                            ->visible(fn (Forms\Get $get) => $get('channel') === 'email')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList',
                                            ]),

                                        Forms\Components\Textarea::make('content.ar')
                                            ->label(__('marketing::marketing.fields.content'))
                                            ->visible(fn (Forms\Get $get) => $get('channel') !== 'email')
                                            ->rows(5)
                                            ->helperText(__('marketing::marketing.helpers.variables')),
                                    ]),
                            ]),
                    ]),

                Forms\Components\Section::make(__('marketing::marketing.sections.whatsapp_settings'))
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_template_name')
                            ->label(__('marketing::marketing.fields.whatsapp_template_name'))
                            ->helperText(__('marketing::marketing.helpers.whatsapp_template')),

                        Forms\Components\TextInput::make('whatsapp_template_namespace')
                            ->label(__('marketing::marketing.fields.whatsapp_namespace')),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('channel') === 'whatsapp')
                    ->collapsible(),

                Forms\Components\Section::make(__('marketing::marketing.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('marketing::marketing.fields.is_active'))
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('marketing::marketing.fields.sort_order'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('marketing::marketing.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('marketing::marketing.fields.name'))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->colors([
                        'success' => 'whatsapp',
                        'info' => 'sms',
                        'primary' => 'email',
                    ]),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('marketing::marketing.fields.type'))
                    ->formatStateUsing(fn (string $state) => MessageTemplate::types()[$state] ?? $state),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('marketing::marketing.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('marketing::marketing.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->options(MessageTemplate::channels()),

                Tables\Filters\SelectFilter::make('type')
                    ->label(__('marketing::marketing.fields.type'))
                    ->options(MessageTemplate::types()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('marketing::marketing.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label(__('marketing::marketing.actions.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (MessageTemplate $record) {
                        $new = $record->replicate();
                        $new->code = $record->code . '_copy';
                        $new->is_system = false;
                        $new->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageTemplates::route('/'),
            'create' => Pages\CreateMessageTemplate::route('/create'),
            'edit' => Pages\EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
