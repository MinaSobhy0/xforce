<?php

namespace Modules\Payroll\Filament\Resources\SalaryStructureResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Payroll\Models\SalaryRule;
use Modules\Payroll\Models\SalaryRuleCategory;

class RulesRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('payroll::payroll.labels.salary_rules');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('salary_rule_id')
                    ->label(__('payroll::payroll.fields.salary_rule'))
                    ->options(
                        SalaryRule::active()
                            ->ordered()
                            ->get()
                            ->mapWithKeys(fn ($rule) => [
                                $rule->id => "{$rule->code} - {$rule->name}"
                            ])
                    )
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('sequence')
                    ->label(__('payroll::payroll.fields.sequence'))
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('payroll::payroll.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('payroll::payroll.fields.name'))
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('payroll::payroll.fields.category'))
                    ->badge()
                    ->color(fn ($record) => $record->category?->type_color ?? 'gray'),

                Tables\Columns\TextColumn::make('category.type')
                    ->label(__('payroll::payroll.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => SalaryRuleCategory::TYPES[$state] ?? $state)
                    ->color(fn ($state) => SalaryRuleCategory::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('display_value')
                    ->label(__('payroll::payroll.fields.value'))
                    ->getStateUsing(fn ($record) => $record->display_value),

                Tables\Columns\TextColumn::make('sequence')
                    ->label(__('payroll::payroll.fields.sequence'))
                    ->getStateUsing(fn ($record) => $record->pivot?->sequence ?? 0)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('salary_structure_rules.sequence', $direction)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->active()->ordered())
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('sequence')
                            ->label(__('payroll::payroll.fields.sequence'))
                            ->numeric()
                            ->default(fn () => $this->getOwnerRecord()->rules()->max('salary_structure_rules.sequence') + 10)
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('editSequence')
                    ->label(__('payroll::payroll.actions.edit_sequence'))
                    ->icon('heroicon-o-arrows-up-down')
                    ->form([
                        Forms\Components\TextInput::make('sequence')
                            ->label(__('payroll::payroll.fields.sequence'))
                            ->numeric()
                            ->default(fn ($record) => $record->pivot?->sequence ?? 0)
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $this->getOwnerRecord()->rules()->updateExistingPivot($record->id, [
                            'sequence' => $data['sequence'],
                        ]);
                    }),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ])
            ->defaultSort('salary_structure_rules.sequence');
    }
}
