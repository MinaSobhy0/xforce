<table>
    <thead>
        <tr>
            <th colspan="8" style="font-size: 18px; font-weight: bold;">Revenue Analytics Report</th>
        </tr>
        <tr>
            <th colspan="8">
                Period: {{ $period['start'] ?? 'All Time' }} - {{ $period['end'] ?? 'Present' }}
            </th>
        </tr>
        <tr><th colspan="8"></th></tr>
        <tr>
            <th colspan="2" style="font-weight: bold;">Summary Metrics</th>
        </tr>
        <tr>
            <td>Total Revenue (Paid)</td>
            <td>{{ number_format($metrics['total_revenue'], 2) }}</td>
        </tr>
        <tr>
            <td>Pending Revenue</td>
            <td>{{ number_format($metrics['pending_revenue'], 2) }}</td>
        </tr>
        <tr>
            <td>Overdue Revenue</td>
            <td>{{ number_format($metrics['overdue_revenue'], 2) }}</td>
        </tr>
        <tr>
            <td>Monthly Recurring Revenue (MRR)</td>
            <td>{{ number_format($metrics['mrr'], 2) }}</td>
        </tr>
        <tr>
            <td>Annual Recurring Revenue (ARR)</td>
            <td>{{ number_format($metrics['arr'], 2) }}</td>
        </tr>
        <tr>
            <td>Average Revenue per Tenant</td>
            <td>{{ number_format($metrics['average_revenue_per_tenant'], 2) }}</td>
        </tr>
        <tr>
            <td>Total Invoices</td>
            <td>{{ $metrics['invoice_count'] }}</td>
        </tr>
        <tr>
            <td>Paid Invoices</td>
            <td>{{ $metrics['paid_count'] }}</td>
        </tr>
        <tr><th colspan="8"></th></tr>
        <tr><th colspan="8"></th></tr>
    </thead>
    <tbody>
        <tr style="font-weight: bold; background-color: #f0f0f0;">
            <td>Invoice #</td>
            <td>Tenant</td>
            <td>Period</td>
            <td>Amount</td>
            <td>Overage</td>
            <td>Status</td>
            <td>Due Date</td>
            <td>Paid At</td>
        </tr>
        @foreach($invoices as $invoice)
        <tr>
            <td>{{ $invoice->invoice_number }}</td>
            <td>{{ $invoice->tenant?->name ?? 'N/A' }}</td>
            <td>{{ $invoice->billing_period }}</td>
            <td>{{ number_format($invoice->amount, 2) }}</td>
            <td>{{ number_format($invoice->overage_amount ?? 0, 2) }}</td>
            <td>{{ ucfirst($invoice->status) }}</td>
            <td>{{ $invoice->due_date?->format('Y-m-d') ?? 'N/A' }}</td>
            <td>{{ $invoice->paid_at?->format('Y-m-d') ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
