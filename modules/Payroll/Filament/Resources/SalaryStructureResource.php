<?php

namespace Modules\Payroll\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Filament\Resources\SalaryStructureResource\Pages;
use Modules\Payroll\Filament\Resources\SalaryStructureResource\RelationManagers;

class SalaryStructureResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = SalaryStructure::class;

    protected static ?string $moduleCode = 'payroll';

    protected static ?string $permissionKey = 'payroll';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('payroll::payroll.navigation.salary_structures');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::payroll.labels.salary_structure');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::payroll.labels.salary_structures');
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

                        Forms\Components\Textarea::make('description')
                            ->label(__('payroll::payroll.fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('payroll::payroll.sections.settings'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('pay_frequency')
                                    ->label(__('payroll::payroll.fields.pay_frequency'))
                                    ->options(SalaryStructure::PAY_FREQUENCIES)
                                    ->required()
                                    ->native(false)
                                    ->default(SalaryStructure::PAY_FREQUENCY_MONTHLY),

                                Forms\Components\TextInput::make('currency')
                                    ->label(__('payroll::payroll.fields.currency'))
                                    ->maxLength(10)
                                    ->default(fn () => current_currency()),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('payroll::payroll.fields.is_active'))
                                    ->default(true),
                            ]),
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

                Tables\Columns\TextColumn::make('pay_frequency')
                    ->label(__('payroll::payroll.fields.pay_frequency'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => SalaryStructure::PAY_FREQUENCIES[$state] ?? $state)
                    ->color(fn ($state) => SalaryStructure::PAY_FREQUENCY_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('rules_count')
                    ->label(__('payroll::payroll.fields.rules_count'))
                    ->counts('rules')
                    ->sortable(),

                Tables\Columns\TextColumn::make('active_employee_count')
                    ->label(__('payroll::payroll.fields.employees_count'))
                    ->getStateUsing(fn ($record) => $record->active_employee_count)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('currency')
                    ->label(__('payroll::payroll.fields.currency'))
                    ->toggleable(isToggledHiddenByDefault: true),

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
                Tables\Filters\SelectFilter::make('pay_frequency')
                    ->label(__('payroll::payroll.fields.pay_frequency'))
                    ->options(SalaryStructure::PAY_FREQUENCIES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('payroll::payroll.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label(__('payroll::payroll.actions.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label(__('payroll::payroll.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label(__('payroll::payroll.fields.code'))
                            ->required()
                            ->maxLength(50)
                            ->alphaDash(),
                    ])
                    ->action(function (SalaryStructure $record, array $data): void {
                        $record->duplicate($data['name'], $data['code']);
                        \Filament\Notifications\Notification::make()
                            ->title(__('payroll::payroll.messages.structure_duplicated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (SalaryStructure $record) {
                        if ($record->activeEmployeeStructures()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('payroll::payroll.messages.cannot_delete_structure'))
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
            RelationManagers\RulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalaryStructures::route('/'),
            'create' => Pages\CreateSalaryStructure::route('/create'),
            'view' => Pages\ViewSalaryStructure::route('/{record}'),
            'edit' => Pages\EditSalaryStructure::route('/{record}/edit'),
        ];
    }
}
