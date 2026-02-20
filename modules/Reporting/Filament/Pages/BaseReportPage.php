<?php

namespace Modules\Reporting\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Branch;

abstract class BaseReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'reporting::filament.pages.base-report';

    protected static ?string $navigationGroup = 'Reports';

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $branch_id = null;

    public array $stats = [];
    public array $chartData = [];
    public array $tableData = [];

    public function mount(): void
    {
        // Default to current month
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->endOfMonth()->format('Y-m-d');

        $this->loadReportData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('reporting::reporting.filters'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('reporting::reporting.start_date'))
                            ->default(now()->startOfMonth())
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadReportData()),

                        DatePicker::make('end_date')
                            ->label(__('reporting::reporting.end_date'))
                            ->default(now()->endOfMonth())
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadReportData()),

                        Select::make('branch_id')
                            ->label(__('reporting::reporting.branch'))
                            ->options(Branch::where('is_active', true)->pluck('name', 'id'))
                            ->placeholder(__('reporting::reporting.all_branches'))
                            ->reactive()
                            ->afterStateUpdated(fn () => $this->loadReportData()),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    abstract protected function loadReportData(): void;

    abstract protected function getReportTitle(): string;

    abstract protected function getStatsCards(): array;

    abstract protected function getChartConfig(): array;

    abstract protected function getTableColumns(): array;

    protected function getStartDate(): Carbon
    {
        return Carbon::parse($this->start_date ?? now()->startOfMonth());
    }

    protected function getEndDate(): Carbon
    {
        return Carbon::parse($this->end_date ?? now()->endOfMonth());
    }

    protected function getBranchId(): ?string
    {
        return $this->branch_id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label(__('reporting::reporting.export_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => $this->exportPdf()),

            Action::make('export_excel')
                ->label(__('reporting::reporting.export_excel'))
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn () => $this->exportExcel()),

            Action::make('refresh')
                ->label(__('reporting::reporting.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->loadReportData()),
        ];
    }

    public function exportPdf()
    {
        // PDF export implementation
        // Uses dompdf package
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => __('reporting::reporting.export_started'),
        ]);
    }

    public function exportExcel()
    {
        // Excel export implementation
        // Uses maatwebsite/excel package
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => __('reporting::reporting.export_started'),
        ]);
    }

    protected function formatCurrency(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2) . ' EGP';
    }

    protected function formatPercentage(float $value): string
    {
        return number_format($value, 1) . '%';
    }

    protected function calculatePercentageChange(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
