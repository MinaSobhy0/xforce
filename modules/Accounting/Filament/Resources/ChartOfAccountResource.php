<?php

namespace Modules\Accounting\Filament\Resources;

use App\Traits\ChecksTenantModuleAccess;
use Modules\Accounting\Filament\Resources\ChartOfAccountResource\Pages;
use Modules\Accounting\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class ChartOfAccountResource extends Resource
{
    use ChecksTenantModuleAccess;

    protected static ?string $model = ChartOfAccount::class;

    protected static ?string $moduleCode = 'accounting';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Account Code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('name.en')
                            ->label('Account Name (English)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('name.ar')
                            ->label('Account Name (Arabic)')
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->options(ChartOfAccount::TYPES)
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('sub_type')
                            ->options(ChartOfAccount::SUB_TYPES)
                            ->searchable(),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Account')
                            ->relationship('parent', 'code')
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => $record->display_name)
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('description.en')
                            ->label('Description (English)')
                            ->rows(2),

                        Forms\Components\Textarea::make('description.ar')
                            ->label('Description (Arabic)')
                            ->rows(2),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ChartOfAccount::TYPES[$state] ?? $state)
                    ->color(fn ($state) => ChartOfAccount::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('parent.display_name')
                    ->label('Parent')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('balance_minor')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . config('app.currency_symbol', 'EGP'))
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ChartOfAccount::TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\Filter::make('root_only')
                    ->label('Root Accounts Only')
                    ->query(fn (Builder $query) => $query->root()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ChartOfAccount $record) => !$record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChartOfAccounts::route('/'),
            'create' => Pages\CreateChartOfAccount::route('/create'),
            'edit' => Pages\EditChartOfAccount::route('/{record}/edit'),
        ];
    }
}
