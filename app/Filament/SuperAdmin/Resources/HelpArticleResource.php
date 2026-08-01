<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\HelpArticleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\KnowledgeBase\Models\HelpArticle;
use Modules\KnowledgeBase\Models\HelpCategory;
use Modules\KnowledgeBase\Services\ScreenMappingService;

class HelpArticleResource extends Resource
{
    protected static ?string $model = HelpArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {
        return __('Help Articles');
    }

    public static function getModelLabel(): string
    {
        return __('Help Article');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Help Articles');
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
                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title.en')
                                    ->label('Title (English)')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),

                                Forms\Components\TextInput::make('title.ar')
                                    ->label('Title (Arabic)')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(function () {
                                return HelpCategory::active()
                                    ->ordered()
                                    ->get()
                                    ->mapWithKeys(fn ($cat) => [$cat->id => $cat->full_path]);
                            })
                            ->searchable()
                            ->preload(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('excerpt.en')
                                    ->label('Excerpt (English)')
                                    ->rows(2)
                                    ->helperText('Brief summary shown in search results'),

                                Forms\Components\Textarea::make('excerpt.ar')
                                    ->label('Excerpt (Arabic)')
                                    ->rows(2),
                            ]),

                        Forms\Components\RichEditor::make('content.en')
                            ->label('Content (English)')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3',
                                'bulletList', 'orderedList',
                                'link', 'blockquote', 'codeBlock',
                                'undo', 'redo',
                            ]),

                        Forms\Components\RichEditor::make('content.ar')
                            ->label('Content (Arabic)')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3',
                                'bulletList', 'orderedList',
                                'link', 'blockquote', 'codeBlock',
                                'undo', 'redo',
                            ]),
                    ]),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('screen_key')
                                    ->label('Primary Screen')
                                    ->options($screenOptions)
                                    ->searchable()
                                    ->helperText('Main screen this article is associated with'),

                                Forms\Components\Select::make('panel')
                                    ->label('Panel')
                                    ->options([
                                        'tenant' => 'Tenant (Clinic)',
                                        'super-admin' => 'Super Admin (Platform)',
                                        'admin' => 'Admin (Owner Portal)',
                                    ])
                                    ->default('tenant'),
                            ]),

                        Forms\Components\Select::make('related_screens')
                            ->label('Related Screens')
                            ->options($screenOptions)
                            ->multiple()
                            ->searchable()
                            ->helperText('Additional screens where this article should appear'),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->helperText('Press Enter after each tag'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Featured')
                                    ->helperText('Show in featured section'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                    ]),

                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('view_count')
                                    ->label('Views')
                                    ->content(fn ($record) => $record?->view_count ?? 0),

                                Forms\Components\Placeholder::make('helpful_count')
                                    ->label('Helpful')
                                    ->content(fn ($record) => $record?->helpful_count ?? 0),

                                Forms\Components\Placeholder::make('not_helpful_count')
                                    ->label('Not Helpful')
                                    ->content(fn ($record) => $record?->not_helpful_count ?? 0),
                            ]),
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
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("title->>'en' {$direction}"))
                    ->limit(50),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->getStateUsing(fn ($record) => $record->category?->getTranslation('name', 'en'))
                    ->placeholder('Uncategorized'),

                Tables\Columns\TextColumn::make('screen_key')
                    ->label('Screen')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('panel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tenant' => 'success',
                        'super-admin' => 'danger',
                        'admin' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('Views')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('helpful_count')
                    ->label('Helpful')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name->en')
                    ->preload(),

                Tables\Filters\SelectFilter::make('panel')
                    ->options([
                        'tenant' => 'Tenant',
                        'super-admin' => 'Super Admin',
                        'admin' => 'Admin',
                    ]),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHelpArticles::route('/'),
            'create' => Pages\CreateHelpArticle::route('/create'),
            'edit' => Pages\EditHelpArticle::route('/{record}/edit'),
        ];
    }
}
