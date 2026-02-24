<?php

namespace Modules\Payroll\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Payroll\Models\SalaryRuleCategory;
use Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource\Pages;

class SalaryRuleCategoryResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = SalaryRuleCategory::class;

    protected static ?string $moduleCode = 'payroll';

    protected static ?string $permissionKey = 'payroll';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 52;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('payroll::payroll.navigation.rule_categories');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::payroll.labels.rule_category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::payroll.labels.rule_categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('payroll::payroll.sections.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('payroll::payroll.fields.name'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->label(__('payroll::payroll.fields.code'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->helperText(__('payroll::payroll.help.code_unique')),
                            ]),

                        Forms\Components\Select::make('type')
                            ->label(__('payroll::payroll.fields.type'))
                            ->options(SalaryRuleCategory::TYPES)
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('description')
                            ->label(__('payroll::payroll.fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('payroll::payroll.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('payroll::payroll.fields.is_active'))
                            ->default(true)
                            ->helperText(__('payroll::payroll.help.category_active')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('payroll::payroll.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('payroll::payroll.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('payroll::payroll.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => SalaryRuleCategory::TYPES[$state] ?? $state)
                    ->color(fn ($state) => SalaryRuleCategory::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('rules_count')
                    ->label(__('payroll::payroll.fields.rules_count'))
                    ->counts('rules')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('payroll::payroll.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('payroll::payroll.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('payroll::payroll.fields.type'))
                    ->options(SalaryRuleCategory::TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('payroll::payroll.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (SalaryRuleCategory $record) {
                        if ($record->rules()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('payroll::payroll.messages.cannot_delete_category'))
                                ->danger()
                                ->send();
                            return false;
                        }
                    }),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalaryRuleCategories::route('/'),
            'create' => Pages\CreateSalaryRuleCategory::route('/create'),
            'view' => Pages\ViewSalaryRuleCategory::route('/{record}'),
            'edit' => Pages\EditSalaryRuleCategory::route('/{record}/edit'),
        ];
    }
}
