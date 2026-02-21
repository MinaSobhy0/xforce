<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('billing::billing.pdf.invoice') }} #{{ $meta['code'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 20px;
        }
        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .header-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        .clinic-info {
            color: #666;
            font-size: 11px;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        .invoice-meta {
            font-size: 11px;
            color: #666;
        }
        .invoice-meta strong {
            color: #333;
        }
        .parties {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .bill-to, .bill-from {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .party-title {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        .party-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .party-info {
            font-size: 11px;
            color: #666;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.items th {
            background-color: #4f46e5;
            color: white;
            padding: 10px 8px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            font-size: 11px;
            text-transform: uppercase;
        }
        table.items td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
        }
        table.items tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        .totals {
            width: 300px;
            margin-{{ $isRtl ? 'right' : 'left' }}: auto;
            margin-bottom: 30px;
        }
        .totals-row {
            display: table;
            width: 100%;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .totals-label {
            display: table-cell;
            width: 60%;
            color: #666;
        }
        .totals-value {
            display: table-cell;
            width: 40%;
            text-align: {{ $isRtl ? 'left' : 'right' }};
            font-weight: 500;
        }
        .totals-row.grand-total {
            border-bottom: none;
            border-top: 2px solid #4f46e5;
            padding-top: 10px;
            margin-top: 5px;
        }
        .totals-row.grand-total .totals-label,
        .totals-row.grand-total .totals-value {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-partial { background-color: #fef3c7; color: #92400e; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-draft { background-color: #f3f4f6; color: #374151; }
        .payments-section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        table.payments {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.payments th {
            background-color: #f3f4f6;
            padding: 8px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }
        table.payments td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .notes {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .notes-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="clinic-name">{{ $clinic['name'] }}</div>
                <div class="clinic-info">
                    @if($clinic['address'])<div>{{ $clinic['address'] }}</div>@endif
                    @if($clinic['phone'])<div>{{ __('billing::billing.pdf.phone') }}: {{ $clinic['phone'] }}</div>@endif
                    @if($clinic['email'])<div>{{ __('billing::billing.pdf.email') }}: {{ $clinic['email'] }}</div>@endif
                    @if($clinic['tax_number'])<div>{{ __('billing::billing.pdf.tax_number') }}: {{ $clinic['tax_number'] }}</div>@endif
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">{{ __('billing::billing.pdf.invoice') }}</div>
                <div class="invoice-meta">
                    <div><strong>#{{ $meta['code'] }}</strong></div>
                    <div>{{ __('billing::billing.pdf.date') }}: {{ $meta['date'] }}</div>
                    @if($meta['due_date'])
                    <div>{{ __('billing::billing.pdf.due_date') }}: {{ $meta['due_date'] }}</div>
                    @endif
                    <div style="margin-top: 10px;">
                        <span class="status-badge status-{{ $meta['status'] }}">
                            {{ __('billing::billing.statuses.' . $meta['status']) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parties -->
        <div class="parties">
            <div class="bill-to">
                <div class="party-title">{{ __('billing::billing.pdf.bill_to') }}</div>
                <div class="party-name">{{ $patient['name'] }}</div>
                <div class="party-info">
                    @if($patient['phone'])<div>{{ $patient['phone'] }}</div>@endif
                    @if($patient['email'])<div>{{ $patient['email'] }}</div>@endif
                    @if($patient['address'])<div>{{ $patient['address'] }}</div>@endif
                </div>
            </div>
            @if($branch['name'])
            <div class="bill-from">
                <div class="party-title">{{ __('billing::billing.pdf.branch') }}</div>
                <div class="party-name">{{ $branch['name'] }}</div>
                <div class="party-info">
                    @if($branch['address'])<div>{{ $branch['address'] }}</div>@endif
                    @if($branch['phone'])<div>{{ $branch['phone'] }}</div>@endif
                </div>
            </div>
            @endif
        </div>

        <!-- Line Items -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 40%;">{{ __('billing::billing.pdf.description') }}</th>
                    <th class="text-right" style="width: 10%;">{{ __('billing::billing.pdf.qty') }}</th>
                    <th class="text-right" style="width: 15%;">{{ __('billing::billing.pdf.unit_price') }}</th>
                    <th class="text-right" style="width: 12%;">{{ __('billing::billing.pdf.discount') }}</th>
                    <th class="text-right" style="width: 10%;">{{ __('billing::billing.pdf.tax') }}</th>
                    <th class="text-right" style="width: 13%;">{{ __('billing::billing.pdf.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="text-right">{{ $line['quantity'] }}</td>
                    <td class="text-right">{{ $line['unit_price'] }}</td>
                    <td class="text-right">{{ $line['discount'] }}</td>
                    <td class="text-right">{{ $line['tax'] }}</td>
                    <td class="text-right">{{ $line['total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">{{ __('billing::billing.pdf.subtotal') }}</div>
                <div class="totals-value">{{ $totals['subtotal'] }}</div>
            </div>
            @if($invoice->discount_minor > 0)
            <div class="totals-row">
                <div class="totals-label">{{ __('billing::billing.pdf.discount') }}</div>
                <div class="totals-value">-{{ $totals['discount'] }}</div>
            </div>
            @endif
            @if($invoice->tax_minor > 0)
            <div class="totals-row">
                <div class="totals-label">{{ __('billing::billing.pdf.tax') }}</div>
                <div class="totals-value">{{ $totals['tax'] }}</div>
            </div>
            @endif
            <div class="totals-row grand-total">
                <div class="totals-label">{{ __('billing::billing.pdf.total') }}</div>
                <div class="totals-value">{{ $totals['total'] }}</div>
            </div>
            @if($invoice->paid_minor > 0)
            <div class="totals-row">
                <div class="totals-label">{{ __('billing::billing.pdf.paid') }}</div>
                <div class="totals-value">{{ $totals['paid'] }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label"><strong>{{ __('billing::billing.pdf.balance_due') }}</strong></div>
                <div class="totals-value"><strong>{{ $totals['remaining'] }}</strong></div>
            </div>
            @endif
        </div>

        <!-- Payments -->
        @if($payments->count() > 0)
        <div class="payments-section">
            <div class="section-title">{{ __('billing::billing.pdf.payment_history') }}</div>
            <table class="payments">
                <thead>
                    <tr>
                        <th>{{ __('billing::billing.pdf.date') }}</th>
                        <th>{{ __('billing::billing.pdf.method') }}</th>
                        <th>{{ __('billing::billing.pdf.reference') }}</th>
                        <th class="text-right">{{ __('billing::billing.pdf.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                    <tr>
                        <td>{{ $payment['date'] }}</td>
                        <td>{{ __('billing::billing.payment_methods.' . $payment['method']) }}</td>
                        <td>{{ $payment['reference'] ?? '-' }}</td>
                        <td class="text-right">{{ $payment['amount'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Notes -->
        @if($meta['notes'])
        <div class="notes">
            <div class="notes-title">{{ __('billing::billing.pdf.notes') }}</div>
            <div>{{ $meta['notes'] }}</div>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            @if($meta['footer'])
            <p>{{ $meta['footer'] }}</p>
            @endif
            <p>{{ __('billing::billing.pdf.thank_you') }}</p>
        </div>
    </div>
</body>
</html>
