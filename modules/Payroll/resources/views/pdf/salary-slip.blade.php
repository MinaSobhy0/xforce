<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('payroll::payroll.pdf.salary_slip') }} - {{ $period }}</title>
    <style>
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
            max-width: 100%;
            padding: 20px 30px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #1a365d;
            padding-bottom: 15px;
        }

        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .header-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 5px;
        }

        .company-info {
            font-size: 10px;
            color: #666;
        }

        .slip-title {
            font-size: 16px;
            font-weight: bold;
            color: #1a365d;
            margin-bottom: 5px;
        }

        .period-badge {
            display: inline-block;
            background: #e2e8f0;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            color: #4a5568;
        }

        /* Employee Info Section */
        .employee-section {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-grid {
            display: table;
            width: 100%;
        }

        .info-row {
            display: table-row;
        }

        .info-label, .info-value {
            display: table-cell;
            padding: 4px 8px;
            vertical-align: top;
        }

        .info-label {
            width: 120px;
            font-weight: bold;
            color: #4a5568;
        }

        .info-value {
            color: #2d3748;
        }

        /* Earnings & Deductions */
        .salary-section {
            margin-bottom: 15px;
        }

        .salary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .salary-table th,
        .salary-table td {
            padding: 8px 10px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
            border-bottom: 1px solid #e2e8f0;
        }

        .salary-table th {
            background: #edf2f7;
            font-weight: bold;
            color: #2d3748;
        }

        .salary-table .amount {
            text-align: {{ $isRtl ? 'left' : 'right' }};
            font-family: 'Courier New', monospace;
            width: 120px;
        }

        .salary-table .subtotal {
            background: #f7fafc;
            font-weight: bold;
        }

        .earnings-table th {
            background: #c6f6d5;
            color: #22543d;
        }

        .deductions-table th {
            background: #fed7d7;
            color: #822727;
        }

        /* Net Salary */
        .net-salary-section {
            background: #1a365d;
            color: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .net-salary-grid {
            display: table;
            width: 100%;
        }

        .net-salary-label, .net-salary-value {
            display: table-cell;
            vertical-align: middle;
        }

        .net-salary-label {
            font-size: 14px;
        }

        .net-salary-value {
            text-align: {{ $isRtl ? 'left' : 'right' }};
            font-size: 24px;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .signatures {
            display: table;
            width: 100%;
            margin-top: 40px;
        }

        .signature-box {
            display: table-cell;
            width: 33.33%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #4a5568;
            width: 120px;
            margin: 0 auto 5px;
        }

        .signature-label {
            font-size: 10px;
            color: #666;
        }

        .disclaimer {
            font-size: 9px;
            color: #718096;
            text-align: center;
            margin-top: 20px;
        }

        .generated-at {
            font-size: 9px;
            color: #a0aec0;
            text-align: {{ $isRtl ? 'left' : 'right' }};
            margin-top: 10px;
        }

        /* Two Column Layout */
        .two-columns {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-{{ $isRtl ? 'left' : 'right' }}: 10px;
        }

        .column:last-child {
            padding-{{ $isRtl ? 'left' : 'right' }}: 0;
            padding-{{ $isRtl ? 'right' : 'left' }}: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ $tenant?->name ?? config('app.name') }}</div>
                <div class="company-info">
                    {{ $tenant?->address ?? '' }}<br>
                    {{ $tenant?->phone ?? '' }}
                </div>
            </div>
            <div class="header-right">
                <div class="slip-title">{{ __('payroll::payroll.pdf.salary_slip') }}</div>
                <div class="period-badge">{{ $period }}</div>
            </div>
        </div>

        <!-- Employee Information -->
        <div class="employee-section">
            <div class="section-title">{{ __('payroll::payroll.pdf.employee_details') }}</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">{{ __('payroll::payroll.pdf.employee_name') }}:</div>
                    <div class="info-value">{{ $user?->name ?? '-' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">{{ __('payroll::payroll.pdf.employee_id') }}:</div>
                    <div class="info-value">{{ $staff->employee_id ?? $staff->id }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">{{ __('payroll::payroll.pdf.department') }}:</div>
                    <div class="info-value">{{ $staff->department ?? '-' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">{{ __('payroll::payroll.pdf.branch') }}:</div>
                    <div class="info-value">{{ $staff->branch?->name ?? '-' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">{{ __('payroll::payroll.pdf.pay_period') }}:</div>
                    <div class="info-value">{{ $period }}</div>
                </div>
            </div>
        </div>

        <!-- Two Column Layout: Earnings & Deductions -->
        <div class="two-columns">
            <!-- Earnings Column -->
            <div class="column">
                <div class="salary-section">
                    <table class="salary-table earnings-table">
                        <thead>
                            <tr>
                                <th>{{ __('payroll::payroll.pdf.earnings') }}</th>
                                <th class="amount">{{ __('payroll::payroll.pdf.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ __('payroll::payroll.pdf.base_salary') }}</td>
                                <td class="amount">{{ number_format($baseSalary, 2) }}</td>
                            </tr>
                            @if($commissions > 0)
                            <tr>
                                <td>{{ __('payroll::payroll.pdf.commissions') }}</td>
                                <td class="amount">{{ number_format($commissions, 2) }}</td>
                            </tr>
                            @endif
                            @if($bonuses > 0)
                            <tr>
                                <td>{{ __('payroll::payroll.pdf.bonuses') }}</td>
                                <td class="amount">{{ number_format($bonuses, 2) }}</td>
                            </tr>
                            @endif
                            @foreach($bonusDetails as $bonus)
                            <tr>
                                <td style="padding-{{ $isRtl ? 'right' : 'left' }}: 20px; font-size: 11px;">
                                    {{ $bonus['description'] }}
                                </td>
                                <td class="amount" style="font-size: 11px;">{{ number_format($bonus['amount'], 2) }}</td>
                            </tr>
                            @endforeach
                            <tr class="subtotal">
                                <td>{{ __('payroll::payroll.pdf.gross_salary') }}</td>
                                <td class="amount">{{ number_format($grossSalary, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Deductions Column -->
            <div class="column">
                <div class="salary-section">
                    <table class="salary-table deductions-table">
                        <thead>
                            <tr>
                                <th>{{ __('payroll::payroll.pdf.deductions') }}</th>
                                <th class="amount">{{ __('payroll::payroll.pdf.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($tax > 0)
                            <tr>
                                <td>{{ __('payroll::payroll.pdf.income_tax') }}</td>
                                <td class="amount">{{ number_format($tax, 2) }}</td>
                            </tr>
                            @endif
                            @if($socialInsurance > 0)
                            <tr>
                                <td>{{ __('payroll::payroll.pdf.social_insurance') }}</td>
                                <td class="amount">{{ number_format($socialInsurance, 2) }}</td>
                            </tr>
                            @endif
                            @if($otherDeductions > 0)
                            <tr>
                                <td>{{ __('payroll::payroll.pdf.other_deductions') }}</td>
                                <td class="amount">{{ number_format($otherDeductions, 2) }}</td>
                            </tr>
                            @endif
                            @foreach($deductionDetails as $deduction)
                            <tr>
                                <td style="padding-{{ $isRtl ? 'right' : 'left' }}: 20px; font-size: 11px;">
                                    {{ $deduction['description'] }}
                                </td>
                                <td class="amount" style="font-size: 11px;">{{ number_format($deduction['amount'], 2) }}</td>
                            </tr>
                            @endforeach
                            <tr class="subtotal">
                                <td>{{ __('payroll::payroll.pdf.total_deductions') }}</td>
                                <td class="amount">{{ number_format($totalDeductions, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Net Salary -->
        <div class="net-salary-section">
            <div class="net-salary-grid">
                <div class="net-salary-label">{{ __('payroll::payroll.pdf.net_salary') }}</div>
                <div class="net-salary-value">{{ $currency }} {{ number_format($netSalary, 2) }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ __('payroll::payroll.pdf.employee_signature') }}</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ __('payroll::payroll.pdf.hr_manager') }}</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">{{ __('payroll::payroll.pdf.finance_manager') }}</div>
                </div>
            </div>

            <div class="disclaimer">
                {{ __('payroll::payroll.pdf.disclaimer') }}
            </div>

            <div class="generated-at">
                {{ __('payroll::payroll.pdf.generated_on') }}: {{ $generatedAt->format('Y-m-d H:i:s') }}
            </div>
        </div>
    </div>
</body>
</html>
