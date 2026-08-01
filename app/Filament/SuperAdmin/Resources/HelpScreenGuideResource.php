<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\HelpScreenGuideResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\KnowledgeBase\Models\HelpScreenGuide;
use Modules\KnowledgeBase\Models\HelpGuideStep;
use Modules\KnowledgeBase\Services\ScreenMappingService;

class HelpScreenGuideResource extends Resource
{
    protected static ?string $model = HelpScreenGuide::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return __('Screen Guides');
    }

    public static function getModelLabel(): string
    {
        return __('Screen Guide');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Screen Guides');
    }

    public static function form(Form $form): Form
    {
        $screenMappingService = app(ScreenMappingService::class);
        $screenKeys = $screenMappingService->getScreenKeysGrouped();

        $screenOptions = [];
        foreach ($screenKeys as $module => $screens) {
            foreach ($screens as $screen) {
                $screenOptions[$screen['key']] = ucfirst($module) . ' - ' . $screen['label'];
            }
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Guide Settings')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('screen_key')
                                    ->label('Screen')
                                    ->options($screenOptions)
                                    ->searchable()
                                    ->required(),

                                Forms\Components\Select::make('panel')
                                    ->label('Panel')
                                    ->options([
                                        'tenant' => 'Tenant (Clinic)',
                                        'super-admin' => 'Super Admin (Platform)',
                                        'admin' => 'Admin (Owner Portal)',
                                    ])
                                    ->default('tenant')
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title.en')
                                    ->label('Title (English)')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('title.ar')
                                    ->label('Title (Arabic)')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label('Description (English)')
                                    ->rows(2),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label('Description (Arabic)')
                                    ->rows(2),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\Toggle::make('show_on_first_visit')
                                    ->label('Show on First Visit')
                                    ->default(true)
                                    ->helperText('Automatically show when user first visits this screen'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                    ]),

                Forms\Components\Section::make('Guide Steps')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('title.en')
                                            ->label('Step Title (English)')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('title.ar')
                                            ->label('Step Title (Arabic)')
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Textarea::make('content.en')
                                            ->label('Step Content (English)')
                                            ->required()
                                            ->rows(2),

                                        Forms\Components\Textarea::make('content.ar')
                                            ->label('Step Content (Arabic)')
                                            ->rows(2),
                                    ]),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('target_selector')
                                            ->label('Target Selector')
                                            ->required()
                                            ->placeholder('.fi-btn, #my-element')
                                            ->helperText('CSS selector for element to highlight'),

                                        Forms\Components\Select::make('placement')
                                            ->label('Tooltip Position')
                                            ->options(HelpGuideStep::PLACEMENTS)
                                            ->default('bottom')
                                            ->required(),

                                        Forms\Components\Select::make('action_type')
                                            ->label('Action Type')
                                            ->options(HelpGuideStep::ACTION_TYPES)
                                            ->nullable()
                                            ->placeholder('Info Only'),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('action_selector')
                                            ->label('Action Selector')
                                            ->placeholder('Leave empty to use target selector')
                                            ->helperText('CSS selector for action element'),

                                        Forms\Components\Toggle::make('is_required')
                                            ->label('Required Step')
                                            ->default(false),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? 'New Step')
                            ->addActionLabel('Add Step')
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(0)
                            ->orderColumn('sort_order'),
                    ])
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn ($record) => $record->getTranslation('title', 'en'))
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereRaw("title->>'en' ILIKE ?", ["%{$search}%"]);
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("title->>'en' {$direction}")),

                Tables\Columns\TextColumn::make('screen_key')
                    ->label('Screen')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('panel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tenant' => 'success',
                        'super-admin' => 'danger',
                        'admin' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('steps_count')
                    ->label('Steps')
                    ->counts('steps')
                    ->sortable(),

                Tables\Columns\IconColumn::make('show_on_first_visit')
                    ->label('Auto-show')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('panel')
                    ->options([
                        'tenant' => 'Tenant',
                        'super-admin' => 'Super Admin',
                        'admin' => 'Admin',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('screen_key');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHelpScreenGuides::route('/'),
            'create' => Pages\CreateHelpScreenGuide::route('/create'),
            'edit' => Pages\EditHelpScreenGuide::route('/{record}/edit'),
        ];
    }
}
