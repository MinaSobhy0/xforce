<?php

namespace Modules\Accounting\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
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
    use ChecksResourcePermissions;

    protected static ?string $model = ChartOfAccount::class;

    protected static ?string $moduleCode = 'accounting';

    protected static ?string $permissionKey = 'invoices';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('accounting::accounting.chart_of_accounts');
    }

    public static function getModelLabel(): string
    {
        return __('accounting::accounting.chart_of_account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting::accounting.chart_of_accounts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('accounting::accounting.account_resource.account_code'))
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('name.en')
                            ->label(__('accounting::accounting.account_resource.account_name_en'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('name.ar')
                            ->label(__('accounting::accounting.account_resource.account_name_ar'))
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label(__('accounting::accounting.type'))
                            ->options(ChartOfAccount::TYPES)
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('parent_id')
                            ->label(__('accounting::accounting.account_resource.parent_account'))
                            ->relationship('parent', 'code')
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => $record->display_name)
                            ->searchable()
                            ->preload(),

                        Forms\Components\Textarea::make('description.en')
                            ->label(__('accounting::accounting.account_resource.description_en'))
                            ->rows(2),

                        Forms\Components\Textarea::make('description.ar')
                            ->label(__('accounting::accounting.account_resource.description_ar'))
                            ->rows(2),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('accounting::accounting.statuses.open'))
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
                    ->label(__('accounting::accounting.code'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('accounting::accounting.account_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('accounting::accounting.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ChartOfAccount::TYPES_FLAT[$state] ?? $state)
                    ->color(fn ($state) => ChartOfAccount::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('parent.display_name')
                    ->label(__('accounting::accounting.account_resource.parent'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('balance_minor')
                    ->label(__('accounting::accounting.balance'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->suffix(' ' . current_currency())
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('accounting::accounting.statuses.open'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('accounting::accounting.type'))
                    ->options(ChartOfAccount::TYPES_FLAT),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('accounting::accounting.statuses.open')),

                Tables\Filters\Filter::make('root_only')
                    ->label(__('accounting::accounting.account_resource.root_accounts_only'))
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
