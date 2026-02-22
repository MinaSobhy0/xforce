<?php

namespace Modules\Attendance\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\AttendanceRule;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource\RelationManagers;

class AttendanceRuleResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = AttendanceRule::class;

    protected static ?string $moduleCode = 'attendance';

    protected static ?string $permissionKey = 'attendance_rules';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 22;

    public static function getNavigationLabel(): string
    {
        return __('attendance::attendance.attendance_rules');
    }

    public static function getModelLabel(): string
    {
        return __('attendance::attendance.attendance_rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('attendance::attendance.attendance_rules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rule Information')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('attendance::attendance.rule_name'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->label(__('attendance::attendance.rule_code'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('category')
                                    ->label(__('attendance::attendance.rule_category'))
                                    ->options(AttendanceRule::CATEGORIES)
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('working_schedule_id')
                            ->label(__('attendance::attendance.working_schedule'))
                            ->relationship('workingSchedule', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty for global rule'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2),
                    ]),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('attendance::attendance.is_active'))
                                    ->default(true),

                                Forms\Components\Toggle::make('auto_apply')
                                    ->label(__('attendance::attendance.auto_apply'))
                                    ->helperText('Automatically apply violations'),

                                Forms\Components\TextInput::make('sequence')
                                    ->label('Sequence')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Order of rule evaluation'),
                            ]),
                    ]),

                Forms\Components\Section::make('Notifications')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('send_notification')
                                    ->label(__('attendance::attendance.send_notification'))
                                    ->default(true),

                                Forms\Components\Toggle::make('notify_manager')
                                    ->label(__('attendance::attendance.notify_manager')),

                                Forms\Components\Toggle::make('notify_hr')
                                    ->label(__('attendance::attendance.notify_hr')),
                            ]),
                    ]),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('attendance::attendance.notes'))
                            ->rows(3),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('attendance::attendance.rule_code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('attendance::attendance.rule_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('attendance::attendance.rule_category'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceRule::CATEGORIES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceRule::CATEGORY_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('workingSchedule.name')
                    ->label(__('attendance::attendance.working_schedule'))
                    ->placeholder('Global'),

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

                Tables\Columns\TextColumn::make('violations_count')
                    ->label(__('attendance::attendance.violations'))
                    ->badge()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(AttendanceRule::CATEGORIES),

                Tables\Filters\SelectFilter::make('working_schedule_id')
                    ->relationship('workingSchedule', 'name')
                    ->label(__('attendance::attendance.working_schedule')),

                Tables\Filters\TernaryFilter::make('is_active'),

                Tables\Filters\TernaryFilter::make('auto_apply'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceRules::route('/'),
            'create' => Pages\CreateAttendanceRule::route('/create'),
            'view' => Pages\ViewAttendanceRule::route('/{record}'),
            'edit' => Pages\EditAttendanceRule::route('/{record}/edit'),
        ];
    }
}
