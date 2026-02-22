<?php

namespace Modules\Attendance\Filament\Resources\WorkingScheduleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\AttendanceRule;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $title = 'Attendance Rules';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('attendance::attendance.rule_name'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('code')
                    ->label(__('attendance::attendance.rule_code'))
                    ->required()
                    ->maxLength(50),

                Forms\Components\Select::make('category')
                    ->label(__('attendance::attendance.rule_category'))
                    ->options(AttendanceRule::CATEGORIES)
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('attendance::attendance.is_active'))
                            ->default(true),

                        Forms\Components\Toggle::make('auto_apply')
                            ->label(__('attendance::attendance.auto_apply')),

                        Forms\Components\Toggle::make('send_notification')
                            ->label(__('attendance::attendance.send_notification'))
                            ->default(true),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('notify_manager')
                            ->label(__('attendance::attendance.notify_manager')),

                        Forms\Components\Toggle::make('notify_hr')
                            ->label(__('attendance::attendance.notify_hr')),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('attendance::attendance.rule_code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('attendance::attendance.rule_name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('attendance::attendance.rule_category'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceRule::CATEGORIES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceRule::CATEGORY_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('actions_count')
                    ->label('Actions')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('auto_apply')
                    ->label(__('attendance::attendance.auto_apply'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('attendance::attendance.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(AttendanceRule::CATEGORIES),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = auth()->user()->tenant_id;
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
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
            ->defaultSort('sequence');
    }
}
