<?php

namespace Modules\Services\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Filament\Resources\ServiceCategoryResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class ServiceCategoryResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = ServiceCategory::class;

    protected static ?string $moduleCode = 'services';

    protected static ?string $permissionKey = 'services';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('services::services.navigation.categories');
    }

    public static function getModelLabel(): string
    {
        return __('services::services.labels.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('services::services.labels.categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label(__('services::services.fields.parent_category'))
                            ->relationship(
                                'parent',
                                'id',
                                fn (Builder $query) => $query->roots()->active()
                            )
                            ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->translated_name)
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('services::services.fields.name') . ' (English)')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('services::services.fields.name') . ' (Arabic)')
                                    ->required()
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label(__('services::services.fields.description') . ' (English)')
                                    ->rows(3),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label(__('services::services.fields.description') . ' (Arabic)')
                                    ->rows(3),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('icon')
                                    ->label('Icon')
                                    ->placeholder('heroicon-o-sparkles')
                                    ->maxLength(50),

                                Forms\Components\ColorPicker::make('color')
                                    ->label('Color'),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('services::services.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('services::services.fields.name'))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.translated_name')
                    ->label(__('services::services.fields.parent_category'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->sortable(),

                Tables\Columns\TextColumn::make('children_count')
                    ->label('Subcategories')
                    ->counts('children')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('services::services.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label(__('services::services.fields.parent_category'))
                    ->relationship('parent', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->translated_name),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('services::services.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ServiceCategory $record) => $record->canDelete()),
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
            'index' => Pages\ListServiceCategories::route('/'),
            'create' => Pages\CreateServiceCategory::route('/create'),
            'edit' => Pages\EditServiceCategory::route('/{record}/edit'),
        ];
    }
}
