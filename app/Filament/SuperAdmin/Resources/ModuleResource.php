<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ModuleResource\Pages;
use App\Models\Module;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Resources\Concerns\Translatable;

class ModuleResource extends Resource
{
    use Translatable;

    protected static ?string $model = Module::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Module Registry';

    protected static ?string $navigationGroup = 'Plans & Modules';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('General')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),

                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description'),

                    Forms\Components\TextInput::make('icon_emoji')
                        ->label('Icon Emoji')
                        ->maxLength(10),

                    Forms\Components\Select::make('category')
                        ->options(Module::CATEGORIES)
                        ->required(),

                    Forms\Components\Select::make('tier')
                        ->options(Module::TIERS)
                        ->required(),

                    Forms\Components\TextInput::make('addon_price_monthly_minor')
                        ->label('Add-on Price (piasters)')
                        ->numeric()
                        ->visible(fn(Forms\Get $get) => $get('tier') === 'addon'),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                ]),

            Forms\Components\Section::make('Status')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_core')
                        ->label('Core Module')
                        ->helperText('Core modules cannot be deactivated'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Toggle::make('is_beta')
                        ->label('Beta'),
                ]),

            Forms\Components\Section::make('Dependencies')
                ->schema([
                    Forms\Components\CheckboxList::make('dependencies')
                        ->label('Required Modules')
                        ->options(fn() => Module::whereRaw('is_core = false')->pluck('code', 'code'))
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon_emoji')
                    ->label('')
                    ->width(40),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'core' => 'danger',
                        'operations' => 'info',
                        'financial' => 'success',
                        'sales' => 'warning',
                        'marketing' => 'purple',
                        'advanced' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('tier')
                    ->label('Tier')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'free' => 'success',
                        'starter' => 'info',
                        'professional' => 'warning',
                        'enterprise' => 'danger',
                        'addon' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('adoption_count')
                    ->label('Adoptions')
                    ->getStateUsing(fn(Module $record) => $record->adoption_count . '/' . $record->total_tenants),

                Tables\Columns\IconColumn::make('is_core')
                    ->label('Core')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_beta')
                    ->label('Beta')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(Module::CATEGORIES),
                Tables\Filters\SelectFilter::make('tier')
                    ->options(Module::TIERS),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label(fn(Module $record) => $record->is_active ? 'Disable' : 'Enable')
                    ->icon(fn(Module $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn(Module $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn(Module $record) => !$record->is_core)
                    ->action(function (Module $record) {
                        $record->update(['is_active' => !$record->is_active]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }
}
