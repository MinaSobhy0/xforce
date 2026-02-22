<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\AttendanceRuleAction;

class ActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'actions';

    protected static ?string $title = 'Rule Actions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Action Type')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('action_type')
                                    ->label(__('attendance::attendance.action_type'))
                                    ->options(AttendanceRuleAction::ACTION_TYPES)
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('severity')
                                    ->label(__('attendance::attendance.severity'))
                                    ->options(AttendanceRuleAction::SEVERITIES)
                                    ->default(AttendanceRuleAction::SEVERITY_MODERATE)
                                    ->required(),

                                Forms\Components\TextInput::make('occurrence_number')
                                    ->label(__('attendance::attendance.occurrence_number'))
                                    ->numeric()
                                    ->helperText('e.g., 1 for first offense'),
                            ]),
                    ]),

                Forms\Components\Section::make('Threshold')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('threshold_type')
                                    ->label(__('attendance::attendance.threshold_type'))
                                    ->options(AttendanceRuleAction::THRESHOLD_TYPES)
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('threshold_value')
                                    ->label(__('attendance::attendance.threshold_value'))
                                    ->numeric()
                                    ->required()
                                    ->helperText(fn (Forms\Get $get) =>
                                        $get('threshold_type') === AttendanceRuleAction::THRESHOLD_TIME
                                            ? 'Minutes'
                                            : 'Count'
                                    ),

                                Forms\Components\Select::make('threshold_period')
                                    ->label(__('attendance::attendance.threshold_period'))
                                    ->options(AttendanceRuleAction::PERIODS)
                                    ->visible(fn (Forms\Get $get) => $get('threshold_type') === AttendanceRuleAction::THRESHOLD_OCCURRENCE),
                            ]),
                    ]),

                Forms\Components\Section::make('Penalty')
                    ->schema([
                        Forms\Components\Select::make('penalty_type')
                            ->label(__('attendance::attendance.penalty_type'))
                            ->options(AttendanceRuleAction::PENALTY_TYPES)
                            ->live()
                            ->visible(fn (Forms\Get $get) => $get('action_type') === AttendanceRuleAction::ACTION_DEDUCTION),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('penalty_amount_minor')
                                    ->label(__('attendance::attendance.penalty_amount') . ' (in minor units)')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Amount in smallest currency unit (e.g., cents)')
                                    ->visible(fn (Forms\Get $get) => $get('penalty_type') === AttendanceRuleAction::PENALTY_FIXED),

                                Forms\Components\TextInput::make('penalty_percentage')
                                    ->label(__('attendance::attendance.penalty_percentage'))
                                    ->numeric()
                                    ->suffix('%')
                                    ->visible(fn (Forms\Get $get) => $get('penalty_type') === AttendanceRuleAction::PENALTY_PERCENTAGE),

                                Forms\Components\Textarea::make('penalty_formula')
                                    ->label(__('attendance::attendance.penalty_formula'))
                                    ->rows(2)
                                    ->helperText('e.g., violation_minutes * hourly_salary / 60')
                                    ->visible(fn (Forms\Get $get) => $get('penalty_type') === AttendanceRuleAction::PENALTY_FORMULA),
                            ])
                            ->visible(fn (Forms\Get $get) => $get('action_type') === AttendanceRuleAction::ACTION_DEDUCTION),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('action_type') === AttendanceRuleAction::ACTION_DEDUCTION),

                Forms\Components\Section::make('Workflow')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('requires_approval')
                                    ->label(__('attendance::attendance.requires_approval')),

                                Forms\Components\Toggle::make('notification_enabled')
                                    ->label('Send Notification')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('attendance::attendance.is_active'))
                                    ->default(true),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('notify_manager')
                                    ->label(__('attendance::attendance.notify_manager'))
                                    ->default(true),

                                Forms\Components\Toggle::make('notify_hr')
                                    ->label(__('attendance::attendance.notify_hr')),
                            ]),
                    ]),

                Forms\Components\Section::make('Message')
                    ->schema([
                        Forms\Components\Textarea::make('message_template')
                            ->label(__('attendance::attendance.message_template'))
                            ->rows(2)
                            ->helperText('Template for notification message'),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('attendance::attendance.notes'))
                            ->rows(2),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action_type')
            ->columns([
                Tables\Columns\TextColumn::make('action_type')
                    ->label(__('attendance::attendance.action_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceRuleAction::ACTION_TYPES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceRuleAction::ACTION_TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('severity')
                    ->label(__('attendance::attendance.severity'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceRuleAction::SEVERITIES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceRuleAction::SEVERITY_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('threshold_description')
                    ->label('Threshold'),

                Tables\Columns\TextColumn::make('penalty_description')
                    ->label('Penalty'),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->label(__('attendance::attendance.requires_approval'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('attendance::attendance.is_active'))
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
            ->defaultSort('threshold_value');
    }
}
