<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('accounting::accounting.profit_loss_statement') }} - {{ $startDate }} to {{ $endDate }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 20px;
        }
        .clinic-name {
            font-size: 22px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        .clinic-info {
            color: #666;
            font-size: 10px;
            margin-bottom: 15px;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-top: 15px;
        }
        .report-date {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #4f46e5;
            padding: 10px;
            background-color: #f0f9ff;
            border-{{ $isRtl ? 'right' : 'left' }}: 4px solid #4f46e5;
            margin-bottom: 10px;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.report-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        table.report-table tr:hover {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        .font-mono {
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .code-col {
            width: 15%;
            color: #666;
        }
        .name-col {
            width: 60%;
        }
        .amount-col {
            width: 25%;
        }
        .subtotal-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .subtotal-row td {
            padding: 10px;
            border-top: 2px solid #e5e7eb;
        }
        .net-income-box {
            margin-top: 30px;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .net-income-profit {
            background-color: #dcfce7;
            border: 2px solid #86efac;
        }
        .net-income-loss {
            background-color: #fee2e2;
            border: 2px solid #fca5a5;
        }
        .net-income-label {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
        }
        .net-income-amount {
            font-size: 28px;
            font-weight: bold;
        }
        .net-income-profit .net-income-amount {
            color: #166534;
        }
        .net-income-loss .net-income-amount {
            color: #991b1b;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 9px;
        }
        .empty-message {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="clinic-name">{{ $clinic['name'] }}</div>
            @if($clinic['address'] || $clinic['phone'])
            <div class="clinic-info">
                {{ $clinic['address'] }} @if($clinic['phone']) | {{ $clinic['phone'] }} @endif
            </div>
            @endif
            <div class="report-title">{{ __('accounting::accounting.profit_loss_statement') }}</div>
            <div class="report-date">
                {{ __('accounting::accounting.period') }}: {{ $startDate }} {{ __('accounting::accounting.to') }} {{ $endDate }}
            </div>
        </div>

        <!-- Revenue Section -->
        <div class="section">
            <div class="section-title">{{ __('accounting::accounting.revenue') }}</div>
            <table class="report-table">
                <tbody>
                    @forelse($revenues as $row)
                    <tr>
                        <td class="code-col font-mono">{{ $row['code'] }}</td>
                        <td class="name-col">{{ $row['name'] }}</td>
                        <td class="amount-col text-right font-mono">{{ number_format($row['amount'] / 100, 2) }} EGP</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="empty-message">{{ __('accounting::accounting.no_revenue') }}</td>
                    </tr>
                    @endforelse
                    <tr class="subtotal-row">
                        <td colspan="2">{{ __('accounting::accounting.total_revenue') }}</td>
                        <td class="text-right font-mono">{{ number_format($totalRevenue / 100, 2) }} EGP</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Expenses Section -->
        <div class="section">
            <div class="section-title">{{ __('accounting::accounting.expenses') }}</div>
            <table class="report-table">
                <tbody>
                    @forelse($expenses as $row)
                    <tr>
                        <td class="code-col font-mono">{{ $row['code'] }}</td>
                        <td class="name-col">{{ $row['name'] }}</td>
                        <td class="amount-col text-right font-mono">{{ number_format($row['amount'] / 100, 2) }} EGP</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="empty-message">{{ __('accounting::accounting.no_expenses') }}</td>
                    </tr>
                    @endforelse
                    <tr class="subtotal-row">
                        <td colspan="2">{{ __('accounting::accounting.total_expenses') }}</td>
                        <td class="text-right font-mono">{{ number_format($totalExpenses / 100, 2) }} EGP</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Net Income -->
        <div class="net-income-box {{ $isProfit ? 'net-income-profit' : 'net-income-loss' }}">
            <div class="net-income-label">
                {{ $isProfit ? __('accounting::accounting.net_profit') : __('accounting::accounting.net_loss') }}
            </div>
            <div class="net-income-amount">
                {{ $isProfit ? '' : '(' }}{{ number_format(abs($netIncome) / 100, 2) }} EGP{{ $isProfit ? '' : ')' }}
            </div>
        </div>

        <div class="footer">
            {{ __('accounting::accounting.generated_at') }}: {{ $generatedAt->format('Y-m-d H:i:s') }}
        </div>
    </div>
</body>
</html>
