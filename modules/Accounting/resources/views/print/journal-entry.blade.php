<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('accounting::accounting.journal_entry') }} #{{ $entry->code }}</title>
    <style>
        @php $isRtl = app()->getLocale() === 'ar'; @endphp
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
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
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
        }
        .meta {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        .meta-item {
            margin-bottom: 5px;
        }
        .meta-label {
            color: #666;
            font-size: 11px;
        }
        .meta-value {
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .status-draft { background-color: #f3f4f6; color: #374151; }
        .status-posted { background-color: #dcfce7; color: #166534; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .info-value {
            font-weight: 500;
        }
        .description {
            margin-bottom: 30px;
            padding: 15px;
            background: #fef3c7;
            border-radius: 8px;
        }
        .description-label {
            font-size: 10px;
            color: #92400e;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.lines th {
            background-color: #4f46e5;
            color: white;
            padding: 10px 8px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            font-size: 11px;
            text-transform: uppercase;
        }
        table.lines td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
        }
        table.lines tr:nth-child(even) {
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
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .totals-row.grand-total {
            border-bottom: none;
            border-top: 2px solid #4f46e5;
            padding-top: 10px;
            margin-top: 5px;
            font-size: 14px;
            font-weight: bold;
            color: #4f46e5;
        }
        .balance-check {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .balanced { background-color: #dcfce7; color: #166534; }
        .unbalanced { background-color: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        {{ __('accounting::accounting.actions.print') }}
    </button>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="title">{{ __('accounting::accounting.journal_entry') }}</div>
                <span class="status-badge status-{{ $entry->status }}">
                    {{ $entry->status_label }}
                </span>
            </div>
            <div class="meta">
                <div class="meta-item">
                    <div class="meta-label">{{ __('accounting::accounting.fields.code') }}</div>
                    <div class="meta-value">#{{ $entry->code }}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">{{ __('accounting::accounting.fields.date') }}</div>
                    <div class="meta-value">{{ $entry->date->format('Y-m-d') }}</div>
                </div>
                @if($entry->posted_at)
                <div class="meta-item">
                    <div class="meta-label">{{ __('accounting::accounting.fields.posted_at') }}</div>
                    <div class="meta-value">{{ $entry->posted_at->format('Y-m-d H:i') }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">{{ __('accounting::accounting.fields.journal') }}</div>
                <div class="info-value">{{ $entry->journal?->display_name ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">{{ __('accounting::accounting.fields.fiscal_period') }}</div>
                <div class="info-value">{{ $entry->fiscalPeriod?->name ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">{{ __('accounting::accounting.fields.reference') }}</div>
                <div class="info-value">{{ $entry->reference ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">{{ __('accounting::accounting.fields.created_by') }}</div>
                <div class="info-value">{{ $entry->createdBy?->name ?? '-' }}</div>
            </div>
        </div>

        <!-- Description -->
        @if($entry->description)
        <div class="description">
            <div class="description-label">{{ __('accounting::accounting.fields.description') }}</div>
            <div>{{ $entry->description }}</div>
        </div>
        @endif

        <!-- Journal Lines -->
        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 40%;">{{ __('accounting::accounting.fields.account') }}</th>
                    <th style="width: 20%;">{{ __('accounting::accounting.fields.partner') }}</th>
                    <th class="text-right" style="width: 20%;">{{ __('accounting::accounting.fields.debit') }}</th>
                    <th class="text-right" style="width: 20%;">{{ __('accounting::accounting.fields.credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entry->lines as $line)
                <tr>
                    <td>
                        <strong>{{ $line->account?->code }}</strong>
                        <br>
                        <span style="color: #666;">{{ $line->account?->getTranslation('name', app()->getLocale()) }}</span>
                        @if($line->description)
                        <br><small style="color: #999;">{{ $line->description }}</small>
                        @endif
                    </td>
                    <td>
                        @if($line->partner)
                            {{ $line->partner->full_name ?? $line->partner->name ?? $line->partner->getTranslation('name', app()->getLocale()) ?? '-' }}
                            <br><small style="color: #666;">({{ class_basename($line->partner_type) }})</small>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($line->debit_minor > 0)
                            {{ number_format($line->debit_minor / 100, 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if($line->credit_minor > 0)
                            {{ number_format($line->credit_minor / 100, 2) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-row grand-total">
                <span>{{ __('accounting::accounting.fields.total_debit') }}</span>
                <span>{{ number_format($entry->total_debit_minor / 100, 2) }} {{ current_currency() }}</span>
            </div>
            <div class="totals-row grand-total">
                <span>{{ __('accounting::accounting.fields.total_credit') }}</span>
                <span>{{ number_format($entry->total_credit_minor / 100, 2) }} {{ current_currency() }}</span>
            </div>
        </div>

        <!-- Balance Check -->
        <div class="balance-check {{ $entry->isBalanced() ? 'balanced' : 'unbalanced' }}">
            @if($entry->isBalanced())
                {{ __('accounting::accounting.messages.entry_balanced') }}
            @else
                {{ __('accounting::accounting.messages.entry_unbalanced') }}
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>{{ __('accounting::accounting.messages.printed_on') }}: {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
