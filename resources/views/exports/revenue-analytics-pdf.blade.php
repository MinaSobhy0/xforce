<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Revenue Analytics Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563EB;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2563EB;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        .metrics-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .metric-card {
            display: table-cell;
            width: 25%;
            padding: 15px;
            text-align: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .metric-value {
            font-size: 20px;
            font-weight: bold;
            color: #2563EB;
        }
        .metric-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #2563EB;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        tr:nth-child(even) {
            background: #f8fafc;
        }
        .status-paid {
            color: #16a34a;
            font-weight: 600;
        }
        .status-pending {
            color: #ca8a04;
            font-weight: 600;
        }
        .status-overdue {
            color: #dc2626;
            font-weight: 600;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #64748b;
            padding: 10px 0;
            border-top: 1px solid #e2e8f0;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Revenue Analytics Report</h1>
        <p>Period: {{ $period['start'] ?? 'All Time' }} - {{ $period['end'] ?? 'Present' }}</p>
        <p>Generated: {{ now()->format('F j, Y H:i') }}</p>
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-value">{{ number_format($metrics['total_revenue'], 0) }}</div>
            <div class="metric-label">Total Revenue</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{ number_format($metrics['mrr'], 0) }}</div>
            <div class="metric-label">MRR</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{ number_format($metrics['arr'], 0) }}</div>
            <div class="metric-label">ARR</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">{{ $metrics['paid_count'] }}</div>
            <div class="metric-label">Paid Invoices</div>
        </div>
    </div>

    <h3>Invoice Details</h3>
    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Tenant</th>
                <th>Period</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Overage</th>
                <th>Status</th>
                <th>Due Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->tenant?->name ?? 'N/A' }}</td>
                <td>{{ ucfirst($invoice->billing_period ?? 'N/A') }}</td>
                <td class="text-right">{{ number_format($invoice->amount, 2) }}</td>
                <td class="text-right">{{ number_format($invoice->overage_amount ?? 0, 2) }}</td>
                <td class="status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</td>
                <td>{{ $invoice->due_date?->format('M j, Y') ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        XLinic Platform - Confidential Report - Page 1
    </div>
</body>
</html>
