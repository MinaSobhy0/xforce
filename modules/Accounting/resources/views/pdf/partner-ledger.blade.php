<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('accounting::accounting.partner_ledger') }} - {{ $startDate }} {{ __('accounting::accounting.to') }} {{ $endDate }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 15px;
        }
        .clinic-name {
            font-size: 20px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        .clinic-info {
            color: #666;
            font-size: 9px;
            margin-bottom: 10px;
        }
        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
        }
        .report-date {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .stats-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
        }
        .stat-box {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
            border-right: 1px solid #e5e7eb;
        }
        .stat-box:last-child {
            border-right: none;
        }
        .stat-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #4f46e5;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .partner-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .partner-header {
            background-color: #4f46e5;
            color: white;
            padding: 10px 12px;
            border-radius: 5px 5px 0 0;
            display: table;
            width: 100%;
        }
        .partner-name {
            display: table-cell;
            font-weight: bold;
            font-size: 12px;
        }
        .partner-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            background-color: rgba(255,255,255,0.2);
            margin-{{ $isRtl ? 'right' : 'left' }}: 10px;
        }
        .partner-opening {
            display: table-cell;
            text-align: {{ $isRtl ? 'left' : 'right' }};
            font-size: 11px;
        }
        table.ledger-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        table.ledger-table th {
            background-color: #f3f4f6;
            padding: 8px 6px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            font-size: 9px;
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
        }
        table.ledger-table th.text-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        table.ledger-table td {
            padding: 6px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 9px;
        }
        table.ledger-table tr:nth-child(even) {
            background-color: #fafafa;
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
            background-color: #f3f4f6 !important;
            font-weight: bold;
        }
        .totals-row td {
            border-top: 2px solid #d1d5db;
            padding: 8px 6px;
        }
        .closing-row {
            background-color: #eef2ff !important;
        }
        .closing-row td {
            padding: 10px 6px;
            font-weight: bold;
            font-size: 11px;
        }
        .text-success {
            color: #059669;
        }
        .text-danger {
            color: #dc2626;
        }
        .reference-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 8px;
            background-color: #dbeafe;
            color: #1e40af;
        }
        .no-entries {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 8px;
        }
        .page-break {
            page-break-before: always;
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
            <div class="report-title">{{ __('accounting::accounting.partner_ledger') }}</div>
            <div class="report-date">
                {{ __('accounting::accounting.period') }}: {{ $startDate }} {{ __('accounting::accounting.to') }} {{ $endDate }}
                @if($partnerType)
                    | {{ $partnerType === 'customer' ? __('accounting::accounting.customers') : __('accounting::accounting.suppliers') }}
                @endif
            </div>
        </div>

        {{-- Stats Summary --}}
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-label">{{ __('accounting::accounting.total_partners') }}</div>
                <div class="stat-value">{{ $stats['total_partners'] }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">{{ __('accounting::accounting.total_debit') }}</div>
                <div class="stat-value text-success">{{ number_format($stats['total_debit'] / 100, 2) }} EGP</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">{{ __('accounting::accounting.total_credit') }}</div>
                <div class="stat-value text-danger">{{ number_format($stats['total_credit'] / 100, 2) }} EGP</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">{{ __('accounting::accounting.net_balance') }}</div>
                <div class="stat-value {{ $stats['net_balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($stats['net_balance'] / 100, 2) }} EGP
                </div>
            </div>
        </div>

        {{-- Partner Ledger Data --}}
        @forelse($partnerData as $index => $partner)
            @if($index > 0 && $index % 3 === 0)
                <div class="page-break"></div>
            @endif

            <div class="partner-section">
                <div class="partner-header">
                    <span class="partner-name">
                        {{ $partner['partner_name'] }}
                        <span class="partner-type">{{ $partner['partner_type_label'] }}</span>
                    </span>
                    <span class="partner-opening">
                        {{ __('accounting::accounting.opening_balance') }}:
                        <strong class="{{ $partner['opening_balance'] >= 0 ? '' : 'text-danger' }}">
                            {{ number_format($partner['opening_balance'] / 100, 2) }} EGP
                        </strong>
                    </span>
                </div>

                @if(count($partner['transactions']) > 0)
                    <table class="ledger-table">
                        <thead>
                            <tr>
                                <th style="width: 12%;">{{ __('accounting::accounting.date') }}</th>
                                <th style="width: 15%;">{{ __('accounting::accounting.reference') }}</th>
                                <th style="width: 33%;">{{ __('accounting::accounting.description') }}</th>
                                <th style="width: 13%;" class="text-right">{{ __('accounting::accounting.debit') }}</th>
                                <th style="width: 13%;" class="text-right">{{ __('accounting::accounting.credit') }}</th>
                                <th style="width: 14%;" class="text-right">{{ __('accounting::accounting.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($partner['transactions'] as $transaction)
                                <tr>
                                    <td class="font-mono">{{ $transaction['date'] }}</td>
                                    <td>
                                        <span class="reference-badge">{{ $transaction['reference'] }}</span>
                                    </td>
                                    <td>{{ $transaction['description'] ?? '-' }}</td>
                                    <td class="text-right font-mono text-success">
                                        {{ $transaction['debit'] > 0 ? number_format($transaction['debit'] / 100, 2) : '-' }}
                                    </td>
                                    <td class="text-right font-mono text-danger">
                                        {{ $transaction['credit'] > 0 ? number_format($transaction['credit'] / 100, 2) : '-' }}
                                    </td>
                                    <td class="text-right font-mono {{ $transaction['balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($transaction['balance'] / 100, 2) }}
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="totals-row">
                                <td colspan="3">{{ __('accounting::accounting.totals') }}</td>
                                <td class="text-right font-mono text-success">
                                    {{ number_format($partner['total_debit'] / 100, 2) }}
                                </td>
                                <td class="text-right font-mono text-danger">
                                    {{ number_format($partner['total_credit'] / 100, 2) }}
                                </td>
                                <td></td>
                            </tr>
                            <tr class="closing-row">
                                <td colspan="5">{{ __('accounting::accounting.closing_balance') }}</td>
                                <td class="text-right font-mono {{ $partner['closing_balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($partner['closing_balance'] / 100, 2) }} EGP
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <div class="no-entries">
                        {{ __('accounting::accounting.no_entries') }}
                        <br>
                        {{ __('accounting::accounting.closing_balance') }}:
                        <strong class="{{ $partner['closing_balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ number_format($partner['closing_balance'] / 100, 2) }} EGP
                        </strong>
                    </div>
                @endif
            </div>
        @empty
            <div class="no-entries" style="border: 1px solid #e5e7eb; border-radius: 5px;">
                {{ __('accounting::accounting.no_partner_activity') }}
            </div>
        @endforelse

        <div class="footer">
            {{ __('accounting::accounting.generated_at') }}: {{ $generatedAt->format('Y-m-d H:i:s') }}
        </div>
    </div>
</body>
</html>
