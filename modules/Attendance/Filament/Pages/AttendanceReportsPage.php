<?php

namespace Modules\Attendance\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\AttendanceViolation;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\StaffProfile;

class AttendanceReportsPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'attendance';

    protected static ?string $permissionKey = 'attendance_reports';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'attendance::filament.pages.attendance-reports';

    public ?string $reportType = 'daily';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?string $branchId = null;
    public ?string $staffProfileId = null;

    // Statistics
    public array $stats = [];
    public array $chartData = [];

    public static function getNavigationLabel(): string
    {
        return __('attendance::attendance.reports.navigation');
    }

    public function getTitle(): string
    {
        return __('attendance::attendance.reports.title');
    }

    public function mount(): void
    {
        $this->dateFrom = today()->startOfMonth()->format('Y-m-d');
        $this->dateTo = today()->format('Y-m-d');
        $this->loadStatistics();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(5)
                    ->schema([
                        Forms\Components\Select::make('reportType')
                            ->label(__('attendance::attendance.reports.report_type'))
                            ->options([
                                'daily' => __('attendance::attendance.reports.types.daily'),
                                'weekly' => __('attendance::attendance.reports.types.weekly'),
                                'monthly' => __('attendance::attendance.reports.types.monthly'),
                                'custom' => __('attendance::attendance.reports.types.custom'),
                            ])
                            ->default('daily')
                            ->live()
                            ->afterStateUpdated(fn () => $this->updateDateRange()),

                        Forms\Components\DatePicker::make('dateFrom')
                            ->label(__('attendance::attendance.reports.date_from'))
                            ->native(false)
                            ->live(),

                        Forms\Components\DatePicker::make('dateTo')
                            ->label(__('attendance::attendance.reports.date_to'))
                            ->native(false)
                            ->live(),

                        Forms\Components\Select::make('branchId')
                            ->label(__('attendance::attendance.branch'))
                            ->options(fn () => Branch::active()->pluck('name', 'id'))
                            ->placeholder(__('attendance::attendance.reports.all_branches'))
                            ->live(),

                        Forms\Components\Select::make('staffProfileId')
                            ->label(__('attendance::attendance.staff'))
                            ->options(function () {
                                return StaffProfile::query()
                                    ->with('user')
                                    ->get()
                                    ->mapWithKeys(fn ($staff) => [$staff->id => $staff->user->name ?? $staff->employee_code]);
                            })
                            ->searchable()
                            ->placeholder(__('attendance::attendance.reports.all_staff'))
                            ->live(),
                    ]),

                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('generate')
                        ->label(__('attendance::attendance.reports.generate'))
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn () => $this->loadStatistics())
                        ->color('primary'),

                    Forms\Components\Actions\Action::make('export')
                        ->label(__('attendance::attendance.reports.export'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn () => $this->exportReport())
                        ->color('success'),
                ])->fullWidth(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getReportQuery())
            ->columns([
                Tables\Columns\TextColumn::make('attendance_date')
                    ->label(__('attendance::attendance.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('staffProfile.user.name')
                    ->label(__('attendance::attendance.staff'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_in_time')
                    ->label(__('attendance::attendance.check_in'))
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out_time')
                    ->label(__('attendance::attendance.check_out'))
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('working_hours')
                    ->label(__('attendance::attendance.working_hours'))
                    ->numeric(2)
                    ->suffix(' hrs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('overtime_hours')
                    ->label(__('attendance::attendance.overtime_hours'))
                    ->numeric(2)
                    ->suffix(' hrs')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_hours')
                    ->label(__('attendance::attendance.late_hours'))
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('attendance_type')
                    ->label(__('attendance::attendance.type'))
                    ->colors(Attendance::TYPE_COLORS),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->colors(Attendance::STATUS_COLORS),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('attendance_type')
                    ->label(__('attendance::attendance.type'))
                    ->options(Attendance::TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->options(Attendance::STATUSES),
            ])
            ->defaultSort('attendance_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected function getReportQuery(): Builder
    {
        $query = Attendance::query()
            ->with(['staffProfile.user', 'branch']);

        if ($this->dateFrom) {
            $query->where('attendance_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('attendance_date', '<=', $this->dateTo);
        }

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        if ($this->staffProfileId) {
            $query->where('staff_profile_id', $this->staffProfileId);
        }

        return $query;
    }

    public function updateDateRange(): void
    {
        $today = today();

        switch ($this->reportType) {
            case 'daily':
                $this->dateFrom = $today->format('Y-m-d');
                $this->dateTo = $today->format('Y-m-d');
                break;
            case 'weekly':
                $this->dateFrom = $today->startOfWeek()->format('Y-m-d');
                $this->dateTo = $today->endOfWeek()->format('Y-m-d');
                break;
            case 'monthly':
                $this->dateFrom = $today->startOfMonth()->format('Y-m-d');
                $this->dateTo = $today->endOfMonth()->format('Y-m-d');
                break;
            // custom - don't change dates
        }

        $this->loadStatistics();
    }

    public function loadStatistics(): void
    {
        $query = $this->getReportQuery();

        // Basic counts - use 'status' field with STATUS_* constants
        $totalRecords = (clone $query)->count();
        $presentCount = (clone $query)->where('status', Attendance::STATUS_PRESENT)->count();
        $absentCount = (clone $query)->where('status', Attendance::STATUS_ABSENT)->count();
        $lateCount = (clone $query)->where('late_hours', '>', 0)->count();
        $leaveCount = (clone $query)->where('status', Attendance::STATUS_LEAVE)->count();
        $halfDayCount = (clone $query)->where('status', Attendance::STATUS_HALF_DAY)->count();

        // Hours calculations
        $totalWorkingHours = (clone $query)->sum('working_hours');
        $totalOvertimeHours = (clone $query)->sum('overtime_hours');
        $avgWorkingHours = $totalRecords > 0 ? round($totalWorkingHours / $totalRecords, 2) : 0;

        // Late minutes
        $totalLateMinutes = (clone $query)->sum('late_hours');

        // Violations
        $violationsQuery = AttendanceViolation::query()
            ->whereHas('attendance', function ($q) {
                if ($this->dateFrom) {
                    $q->where('attendance_date', '>=', $this->dateFrom);
                }
                if ($this->dateTo) {
                    $q->where('attendance_date', '<=', $this->dateTo);
                }
                if ($this->branchId) {
                    $q->where('branch_id', $this->branchId);
                }
                if ($this->staffProfileId) {
                    $q->where('staff_profile_id', $this->staffProfileId);
                }
            });

        $violationsCount = (clone $violationsQuery)->count();
        $pendingViolations = (clone $violationsQuery)->where('status', AttendanceViolation::STATUS_PENDING)->count();
        $totalPenalties = (clone $violationsQuery)->where('status', AttendanceViolation::STATUS_APPLIED)->sum('penalty_amount_minor');

        $this->stats = [
            'total_records' => $totalRecords,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'late_count' => $lateCount,
            'leave_count' => $leaveCount,
            'half_day_count' => $halfDayCount,
            'total_working_hours' => round($totalWorkingHours, 2),
            'total_overtime_hours' => round($totalOvertimeHours, 2),
            'avg_working_hours' => $avgWorkingHours,
            'total_late_hours' => $totalLateMinutes,
            'violations_count' => $violationsCount,
            'pending_violations' => $pendingViolations,
            'total_penalties' => number_format($totalPenalties / 100, 2),
            'attendance_rate' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0,
        ];

        // Chart data - daily breakdown
        $this->chartData = $this->buildChartData();
    }

    protected function buildChartData(): array
    {
        if (!$this->dateFrom || !$this->dateTo) {
            return [];
        }

        $data = Attendance::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->when($this->staffProfileId, fn ($q) => $q->where('staff_profile_id', $this->staffProfileId))
            ->whereBetween('attendance_date', [$this->dateFrom, $this->dateTo])
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->select([
                'attendance_date',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN attendance_type = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN attendance_type = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN attendance_type = 'late' THEN 1 ELSE 0 END) as late"),
            ])
            ->get();

        return $data->map(fn ($row) => [
            'date' => Carbon::parse($row->attendance_date)->format('M d'),
            'present' => $row->present,
            'absent' => $row->absent,
            'late' => $row->late,
            'total' => $row->total,
        ])->toArray();
    }

    public function exportReport()
    {
        // For now, return a CSV download
        $data = $this->getReportQuery()
            ->with(['staffProfile.user', 'branch'])
            ->get();

        $filename = 'attendance_report_' . $this->dateFrom . '_to_' . $this->dateTo . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'Date',
                'Staff Name',
                'Employee Code',
                'Branch',
                'Check In',
                'Check Out',
                'Working Hours',
                'Overtime Hours',
                'Late Minutes',
                'Type',
                'Status',
            ]);

            // Data rows
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->attendance_date?->format('Y-m-d'),
                    $row->staffProfile?->user?->name ?? '-',
                    $row->staffProfile?->employee_code ?? '-',
                    $row->branch?->name ?? '-',
                    $row->check_in_time?->format('H:i') ?? '-',
                    $row->check_out_time?->format('H:i') ?? '-',
                    $row->working_hours ?? 0,
                    $row->overtime_hours ?? 0,
                    $row->late_hours ?? 0,
                    $row->attendance_type ?? '-',
                    $row->status ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
