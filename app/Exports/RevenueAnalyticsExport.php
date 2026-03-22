<?php

namespace App\Exports;

use App\Models\PlatformInvoice;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RevenueAnalyticsExport implements FromView, WithTitle, ShouldAutoSize
{
    protected ?string $startDate;
    protected ?string $endDate;

    public function __construct(?string $startDate = null, ?string $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        $query = PlatformInvoice::query()
            ->with('tenant');

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        // SECURITY: Limit results to prevent DoS via unbounded exports
        $maxRecords = config('app.max_export_rows', 10000);
        $invoices = $query->limit($maxRecords)->get();

        $metrics = [
            'total_revenue' => $invoices->where('status', 'paid')->sum('amount'),
            'pending_revenue' => $invoices->where('status', 'pending')->sum('amount'),
            'overdue_revenue' => $invoices->where('status', 'overdue')->sum('amount'),
            'mrr' => $this->calculateMRR($invoices),
            'arr' => $this->calculateMRR($invoices) * 12,
            'average_revenue_per_tenant' => $invoices->where('status', 'paid')->avg('amount') ?? 0,
            'invoice_count' => $invoices->count(),
            'paid_count' => $invoices->where('status', 'paid')->count(),
        ];

        return view('exports.revenue-analytics', [
            'invoices' => $invoices,
            'metrics' => $metrics,
            'period' => [
                'start' => $this->startDate,
                'end' => $this->endDate,
            ],
        ]);
    }

    public function title(): string
    {
        return 'Revenue Analytics';
    }

    protected function calculateMRR($invoices): float
    {
        return $invoices
            ->where('status', 'paid')
            ->where('billing_period', 'monthly')
            ->sum('amount');
    }
}
