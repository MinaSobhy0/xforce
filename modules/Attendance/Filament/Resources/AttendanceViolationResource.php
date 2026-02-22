<?php

namespace Modules\Attendance\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\Attendance\Filament\Resources\AttendanceViolationResource\Pages;

class AttendanceViolationResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = AttendanceViolation::class;

    protected static ?string $moduleCode = 'attendance';

    protected static ?string $permissionKey = 'attendance_violations';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 23;

    public static function getNavigationLabel(): string
    {
        return __('attendance::attendance.violations');
    }

    public static function getModelLabel(): string
    {
        return __('attendance::attendance.violation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('attendance::attendance.violations');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', AttendanceViolation::STATUS_PENDING)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Violation Details')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('staff_name')
                                    ->label(__('attendance::attendance.staff'))
                                    ->content(fn (?AttendanceViolation $record) => $record?->staffProfile?->user?->name ?? '-'),

                                Forms\Components\Placeholder::make('violation_type_display')
                                    ->label(__('attendance::attendance.violation_type'))
                                    ->content(fn (?AttendanceViolation $record) => AttendanceViolation::TYPES[$record?->violation_type] ?? '-'),

                                Forms\Components\Placeholder::make('violation_date_display')
                                    ->label(__('attendance::attendance.violation_date'))
                                    ->content(fn (?AttendanceViolation $record) => $record?->violation_date?->format('M d, Y') ?? '-'),
                            ]),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Placeholder::make('scheduled_time_display')
                                    ->label(__('attendance::attendance.scheduled_time'))
                                    ->content(fn (?AttendanceViolation $record) => $record?->scheduled_time?->format('h:i A') ?? '-'),

                                Forms\Components\Placeholder::make('actual_time_display')
                                    ->label(__('attendance::attendance.actual_time'))
                                    ->content(fn (?AttendanceViolation $record) => $record?->actual_time?->format('h:i A') ?? '-'),

                                Forms\Components\Placeholder::make('violation_minutes_display')
                                    ->label(__('attendance::attendance.violation_minutes'))
                                    ->content(fn (?AttendanceViolation $record) => $record?->formatted_violation_duration ?? '-'),

                                Forms\Components\Placeholder::make('penalty_display')
                                    ->label(__('attendance::attendance.penalty_amount'))
                                    ->content(fn (?AttendanceViolation $record) => $record?->formatted_penalty ?? '-'),
                            ]),

                        Forms\Components\Placeholder::make('status_display')
                            ->label(__('attendance::attendance.status'))
                            ->content(fn (?AttendanceViolation $record) => AttendanceViolation::STATUSES[$record?->status] ?? '-'),
                    ]),

                Forms\Components\Section::make('Rule Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('rule_name')
                                    ->label('Applied Rule')
                                    ->content(fn (?AttendanceViolation $record) => $record?->rule?->name ?? 'No rule applied'),

                                Forms\Components\Placeholder::make('penalty_type_display')
                                    ->label(__('attendance::attendance.penalty_type'))
                                    ->content(fn (?AttendanceViolation $record) => $record?->penalty_type ?? '-'),
                            ]),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('employee_notes')
                            ->label(__('attendance::attendance.employee_notes'))
                            ->rows(2),

                        Forms\Components\Textarea::make('manager_notes')
                            ->label(__('attendance::attendance.manager_notes'))
                            ->rows(2),

                        Forms\Components\Placeholder::make('waived_info')
                            ->label('Waived Information')
                            ->content(fn (?AttendanceViolation $record) =>
                                $record?->status === AttendanceViolation::STATUS_WAIVED
                                    ? "By: {$record->waivedBy?->name}, Reason: {$record->waived_reason}"
                                    : null
                            )
                            ->visible(fn (?AttendanceViolation $record) => $record?->status === AttendanceViolation::STATUS_WAIVED),

                        Forms\Components\Placeholder::make('dispute_info')
                            ->label('Dispute Information')
                            ->content(fn (?AttendanceViolation $record) =>
                                $record?->status === AttendanceViolation::STATUS_DISPUTED
                                    ? "Reason: {$record->dispute_reason}"
                                    : null
                            )
                            ->visible(fn (?AttendanceViolation $record) => $record?->status === AttendanceViolation::STATUS_DISPUTED),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('violation_date')
                    ->label(__('attendance::attendance.violation_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staffProfile.user.name')
                    ->label(__('attendance::attendance.staff'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('violation_type')
                    ->label(__('attendance::attendance.violation_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceViolation::TYPES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceViolation::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('formatted_violation_duration')
                    ->label(__('attendance::attendance.violation_minutes')),

                Tables\Columns\TextColumn::make('formatted_penalty')
                    ->label(__('attendance::attendance.penalty_amount')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceViolation::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceViolation::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('rule.name')
                    ->label('Rule')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label(__('attendance::attendance.approved_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->options(AttendanceViolation::STATUSES)
                    ->default(AttendanceViolation::STATUS_PENDING),

                Tables\Filters\SelectFilter::make('violation_type')
                    ->label(__('attendance::attendance.violation_type'))
                    ->options(AttendanceViolation::TYPES),

                Tables\Filters\Filter::make('violation_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('violation_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('violation_date', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label(__('attendance::attendance.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AttendanceViolation $record) => $record->canBeApproved())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('manager_notes')
                            ->label(__('attendance::attendance.manager_notes'))
                            ->rows(2),
                    ])
                    ->action(function (AttendanceViolation $record, array $data) {
                        if ($record->approve(auth()->user(), $data['manager_notes'] ?? null)) {
                            Notification::make()
                                ->title(__('attendance::attendance.violation_approved'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('waive')
                    ->label(__('attendance::attendance.waive'))
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->visible(fn (AttendanceViolation $record) => $record->canBeWaived())
                    ->form([
                        Forms\Components\Textarea::make('waived_reason')
                            ->label(__('attendance::attendance.waived_reason'))
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (AttendanceViolation $record, array $data) {
                        if ($record->waive(auth()->user(), $data['waived_reason'])) {
                            Notification::make()
                                ->title(__('attendance::attendance.violation_waived'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('dispute')
                    ->label(__('attendance::attendance.dispute'))
                    ->icon('heroicon-o-flag')
                    ->color('danger')
                    ->visible(fn (AttendanceViolation $record) => $record->canBeDisputed())
                    ->form([
                        Forms\Components\Textarea::make('dispute_reason')
                            ->label(__('attendance::attendance.dispute_reason'))
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (AttendanceViolation $record, array $data) {
                        if ($record->dispute($data['dispute_reason'])) {
                            Notification::make()
                                ->title(__('attendance::attendance.violation_disputed'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Bulk Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->canBeApproved() && $record->approve(auth()->user())) {
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} violations approved")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('violation_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceViolations::route('/'),
            'view' => Pages\ViewAttendanceViolation::route('/{record}'),
        ];
    }
}
