<?php

namespace Modules\Equipment\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Equipment\Filament\Resources\EquipmentTypeResource\Pages;
use Modules\Equipment\Models\EquipmentType;

class EquipmentTypeResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = EquipmentType::class;

    protected static ?string $moduleCode = 'equipment';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 32;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('equipment::equipment.equipment_types');
    }

    public static function getModelLabel(): string
    {
        return __('equipment::equipment.equipment_type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('equipment::equipment.equipment_types');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label('Name (English)')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label('Name (Arabic)')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('manufacturer')
                                    ->label(__('equipment::equipment.manufacturer'))
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('model')
                                    ->label(__('equipment::equipment.model'))
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('category')
                                    ->label(__('equipment::equipment.category'))
                                    ->options(EquipmentType::CATEGORIES)
                                    ->required()
                                    ->searchable(),

                                Forms\Components\TextInput::make('max_shots')
                                    ->label(__('equipment::equipment.max_shots'))
                                    ->numeric()
                                    ->helperText('Leave empty if not applicable'),
                            ]),

                        Forms\Components\KeyValue::make('specifications')
                            ->label(__('equipment::equipment.specifications'))
                            ->keyLabel('Specification')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Specification')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('image_url')
                            ->label(__('equipment::equipment.image'))
                            ->image()
                            ->directory('equipment-types')
                            ->disk('public'),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('equipment::equipment.active'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=EQ&background=6366f1&color=fff'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('equipment::equipment.name'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('manufacturer')
                    ->label(__('equipment::equipment.manufacturer'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('equipment::equipment.category'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EquipmentType::CATEGORIES[$state] ?? $state),

                Tables\Columns\TextColumn::make('max_shots')
                    ->label(__('equipment::equipment.max_shots'))
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('equipment_count')
                    ->label(__('equipment::equipment.units'))
                    ->counts('equipment')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('equipment::equipment.active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(EquipmentType::CATEGORIES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('equipment::equipment.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentTypes::route('/'),
            'create' => Pages\CreateEquipmentType::route('/create'),
            'edit' => Pages\EditEquipmentType::route('/{record}/edit'),
        ];
    }
}
