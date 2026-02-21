<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('accounting::accounting.trial_balance') }} - {{ $asOfDate }}</title>
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
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.report-table th {
            background-color: #4f46e5;
            color: white;
            padding: 10px 8px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            font-size: 10px;
            text-transform: uppercase;
        }
        table.report-table th.text-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        table.report-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        table.report-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        .text-center {
            text-align: center;
        }
        .font-mono {
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .totals-row {
            background-color: #4f46e5 !important;
            color: white;
            font-weight: bold;
        }
        .totals-row td {
            border-bottom: none;
            padding: 12px 8px;
        }
        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            text-transform: uppercase;
        }
        .type-asset { background-color: #dbeafe; color: #1e40af; }
        .type-liability { background-color: #fee2e2; color: #991b1b; }
        .type-equity { background-color: #dcfce7; color: #166534; }
        .type-revenue { background-color: #fef3c7; color: #92400e; }
        .type-expense { background-color: #fce7f3; color: #9d174d; }
        .status-box {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
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
            <div class="report-title">{{ __('accounting::accounting.trial_balance') }}</div>
            <div class="report-date">{{ __('accounting::accounting.as_of_date') }}: {{ $asOfDate }}</div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 15%;">{{ __('accounting::accounting.account_code') }}</th>
                    <th style="width: 35%;">{{ __('accounting::accounting.account_name') }}</th>
                    <th style="width: 15%;" class="text-center">{{ __('accounting::accounting.type') }}</th>
                    <th style="width: 17.5%;" class="text-right">{{ __('accounting::accounting.debit') }}</th>
                    <th style="width: 17.5%;" class="text-right">{{ __('accounting::accounting.credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($trialBalance as $row)
                <tr>
                    <td class="font-mono">{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-center">
                        <span class="type-badge type-{{ $row['type'] }}">
                            {{ __('accounting::accounting.types.' . $row['type']) }}
                        </span>
                    </td>
                    <td class="text-right font-mono">
                        {{ $row['debit'] > 0 ? number_format($row['debit'] / 100, 2) . ' EGP' : '-' }}
                    </td>
                    <td class="text-right font-mono">
                        {{ $row['credit'] > 0 ? number_format($row['credit'] / 100, 2) . ' EGP' : '-' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 30px; color: #666;">
                        {{ __('accounting::accounting.no_data') }}
                    </td>
                </tr>
                @endforelse

                @if(count($trialBalance) > 0)
                <tr class="totals-row">
                    <td colspan="3">{{ __('accounting::accounting.totals') }}</td>
                    <td class="text-right font-mono">{{ number_format($totalDebit / 100, 2) }} EGP</td>
                    <td class="text-right font-mono">{{ number_format($totalCredit / 100, 2) }} EGP</td>
                </tr>
                @endif
            </tbody>
        </table>

        @if(count($trialBalance) > 0)
        <div class="status-box {{ $isBalanced ? 'status-balanced' : 'status-unbalanced' }}">
            @if($isBalanced)
                {{ __('accounting::accounting.trial_balance_balanced') }}
            @else
                {{ __('accounting::accounting.trial_balance_not_balanced') }}
                ({{ __('accounting::accounting.difference') }}: {{ number_format(abs($totalDebit - $totalCredit) / 100, 2) }} EGP)
            @endif
        </div>
        @endif

        <div class="footer">
            {{ __('accounting::accounting.generated_at') }}: {{ $generatedAt->format('Y-m-d H:i:s') }}
        </div>
    </div>
</body>
</html>
