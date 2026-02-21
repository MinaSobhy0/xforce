<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('accounting::accounting.balance_sheet') }} - {{ $asOfDate }}</title>
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
        .two-column {
            display: table;
            width: 100%;
        }
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 0 10px;
        }
        .column:first-child {
            padding-{{ $isRtl ? 'right' : 'left' }}: 0;
        }
        .column:last-child {
            padding-{{ $isRtl ? 'left' : 'right' }}: 0;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: white;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .section-assets .section-title {
            background-color: #3b82f6;
        }
        .section-liabilities .section-title {
            background-color: #ef4444;
        }
        .section-equity .section-title {
            background-color: #22c55e;
        }
        table.report-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.report-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
        }
        .text-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        .font-mono {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 10px;
        }
        .code-col {
            width: 20%;
            color: #666;
        }
        .name-col {
            width: 50%;
        }
        .amount-col {
            width: 30%;
        }
        .subtotal-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .subtotal-row td {
            padding: 8px;
            border-top: 2px solid #e5e7eb;
            border-bottom: none;
        }
        .grand-total-box {
            margin-top: 30px;
            display: table;
            width: 100%;
        }
        .total-cell {
            display: table-cell;
            width: 50%;
            padding: 15px;
            text-align: center;
            vertical-align: middle;
        }
        .total-assets {
            background-color: #dbeafe;
            border: 2px solid #3b82f6;
        }
        .total-liabilities-equity {
            background-color: #dcfce7;
            border: 2px solid #22c55e;
        }
        .total-label {
            font-size: 11px;
            color: #333;
            margin-bottom: 5px;
        }
        .total-amount {
            font-size: 18px;
            font-weight: bold;
        }
        .total-assets .total-amount {
            color: #1e40af;
        }
        .total-liabilities-equity .total-amount {
            color: #166534;
        }
        .status-box {
            margin-top: 20px;
            padding: 12px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }
        .status-balanced {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .status-unbalanced {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .empty-message {
            padding: 15px;
            text-align: center;
            color: #666;
            font-style: italic;
            font-size: 10px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 9px;
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
            <div class="report-title">{{ __('accounting::accounting.balance_sheet') }}</div>
            <div class="report-date">{{ __('accounting::accounting.as_of_date') }}: {{ $asOfDate }}</div>
        </div>

        <div class="two-column">
            <!-- Left Column: Assets -->
            <div class="column">
                <div class="section section-assets">
                    <div class="section-title">{{ __('accounting::accounting.assets') }}</div>
                    <table class="report-table">
                        <tbody>
                            @forelse($assets as $row)
                            <tr>
                                <td class="code-col font-mono">{{ $row['code'] }}</td>
                                <td class="name-col">{{ $row['name'] }}</td>
                                <td class="amount-col text-right font-mono">{{ number_format($row['amount'] / 100, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="empty-message">{{ __('accounting::accounting.no_assets') }}</td>
                            </tr>
                            @endforelse
                            <tr class="subtotal-row">
                                <td colspan="2">{{ __('accounting::accounting.total_assets') }}</td>
                                <td class="text-right font-mono">{{ number_format($totalAssets / 100, 2) }} EGP</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Liabilities & Equity -->
            <div class="column">
                <!-- Liabilities -->
                <div class="section section-liabilities">
                    <div class="section-title">{{ __('accounting::accounting.liabilities') }}</div>
                    <table class="report-table">
                        <tbody>
                            @forelse($liabilities as $row)
                            <tr>
                                <td class="code-col font-mono">{{ $row['code'] }}</td>
                                <td class="name-col">{{ $row['name'] }}</td>
                                <td class="amount-col text-right font-mono">{{ number_format($row['amount'] / 100, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="empty-message">{{ __('accounting::accounting.no_liabilities') }}</td>
                            </tr>
                            @endforelse
                            <tr class="subtotal-row">
                                <td colspan="2">{{ __('accounting::accounting.total_liabilities') }}</td>
                                <td class="text-right font-mono">{{ number_format($totalLiabilities / 100, 2) }} EGP</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Equity -->
                <div class="section section-equity">
                    <div class="section-title">{{ __('accounting::accounting.equity') }}</div>
                    <table class="report-table">
                        <tbody>
                            @forelse($equity as $row)
                            <tr>
                                <td class="code-col font-mono">{{ $row['code'] }}</td>
                                <td class="name-col">{{ $row['name'] }}</td>
                                <td class="amount-col text-right font-mono">{{ number_format($row['amount'] / 100, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="empty-message">{{ __('accounting::accounting.no_equity') }}</td>
                            </tr>
                            @endforelse
                            <tr class="subtotal-row">
                                <td colspan="2">{{ __('accounting::accounting.total_equity') }}</td>
                                <td class="text-right font-mono">{{ number_format($totalEquity / 100, 2) }} EGP</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Grand Totals -->
        <div class="grand-total-box">
            <div class="total-cell total-assets">
                <div class="total-label">{{ __('accounting::accounting.total_assets') }}</div>
                <div class="total-amount">{{ number_format($totalAssets / 100, 2) }} EGP</div>
            </div>
            <div class="total-cell total-liabilities-equity">
                <div class="total-label">{{ __('accounting::accounting.total_liabilities_equity') }}</div>
                <div class="total-amount">{{ number_format($totalLiabilitiesAndEquity / 100, 2) }} EGP</div>
            </div>
        </div>

        <div class="status-box {{ $isBalanced ? 'status-balanced' : 'status-unbalanced' }}">
            @if($isBalanced)
                {{ __('accounting::accounting.balance_sheet_balanced') }}
            @else
                {{ __('accounting::accounting.balance_sheet_not_balanced') }}
                ({{ __('accounting::accounting.difference') }}: {{ number_format(abs($totalAssets - $totalLiabilitiesAndEquity) / 100, 2) }} EGP)
            @endif
        </div>

        <div class="footer">
            {{ __('accounting::accounting.generated_at') }}: {{ $generatedAt->format('Y-m-d H:i:s') }}
        </div>
    </div>
</body>
</html>
