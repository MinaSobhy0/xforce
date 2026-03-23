<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('reporting::reporting.daily_sales_report') }} - {{ $reportDate }}</title>
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

        /* Header - Same as invoice */
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
        .clinic-logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 28px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        .report-meta {
            font-size: 11px;
            color: #666;
        }
        .report-meta strong {
            color: #333;
        }

        /* Stats Cards */
        .stats-section {
            margin-bottom: 30px;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
        }
        .stats-table td {
            padding: 8px;
            vertical-align: top;
        }
        .stat-card {
            background-color: #f9fafb;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 12px;
        }
        .stat-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .stat-value.text-success { color: #166534; }
        .stat-value.text-info { color: #1e40af; }
        .stat-value.text-warning { color: #92400e; }
        .stat-value.text-primary { color: #4f46e5; }
        .stat-desc {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }

        /* Patient Summary Cards */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary-table td {
            padding: 8px;
            vertical-align: top;
        }
        .summary-card {
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
        }
        .summary-card.success { border-top: 3px solid #22c55e; }
        .summary-card.info { border-top: 3px solid #3b82f6; }
        .summary-card.warning { border-top: 3px solid #f59e0b; }
        .summary-count {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        .summary-label {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .summary-amount {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }
        .summary-amount.success { color: #166534; }
        .summary-amount.info { color: #1e40af; }
        .summary-amount.warning { color: #92400e; }

        /* Section Title - Same as invoice */
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .section-title-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            margin-{{ $isRtl ? 'right' : 'left' }}: 8px;
        }
        .section-title-badge.success { background-color: #dcfce7; color: #166534; }
        .section-title-badge.info { background-color: #dbeafe; color: #1e40af; }
        .section-title-badge.warning { background-color: #fef3c7; color: #92400e; }

        /* Data Tables - Same as invoice */
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

        /* Category Row */
        .category-row td {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 11px;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }

        /* Patient Section */
        .patient-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }

        /* Footer - Same as invoice */
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
        {{-- Header --}}
        <div class="header">
            <div class="header-left">
                @if($clinic['logo'])
                    <img src="{{ $clinic['logo'] }}" class="clinic-logo" alt="">
                @endif
                <div class="clinic-name">{{ $clinic['name'] }}</div>
            </div>
            <div class="header-right">
                <div class="report-title">{{ __('reporting::reporting.daily_sales_report') }}</div>
                <div class="report-meta">
                    <div><strong>{{ __('reporting::reporting.date') }}:</strong> {{ $reportDate }}</div>
                    @if($branchName)
                        <div><strong>{{ __('reporting::reporting.branch') }}:</strong> {{ $branchName }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Patient Summary Cards --}}
        <table class="summary-table">
            <tr>
                <td width="33.33%">
                    <div class="summary-card success">
                        <div class="summary-count">{{ $totals['new_patients_count'] }}</div>
                        <div class="summary-label">{{ __('reporting::reporting.new_patients') }}</div>
                        <div class="summary-amount success">{{ format_money($totals['new_patients_total']) }}</div>
                    </div>
                </td>
                <td width="33.33%">
                    <div class="summary-card info">
                        <div class="summary-count">{{ $totals['returning_patients_count'] }}</div>
                        <div class="summary-label">{{ __('reporting::reporting.returning_patients') }}</div>
                        <div class="summary-amount info">{{ format_money($totals['returning_patients_total']) }}</div>
                    </div>
                </td>
                <td width="33.33%">
                    <div class="summary-card warning">
                        <div class="summary-count">{{ $totals['followup_patients_count'] }}</div>
                        <div class="summary-label">{{ __('reporting::reporting.followup_patients') }}</div>
                        <div class="summary-amount warning">{{ format_money($totals['followup_patients_total']) }}</div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Stats Section --}}
        <table class="stats-table">
            <tr>
                <td width="25%">
                    <div class="stat-card">
                        <div class="stat-label">{{ __('reporting::reporting.total_revenue') }}</div>
                        <div class="stat-value text-success">{{ $totals['total_revenue'] }}</div>
                    </div>
                </td>
                <td width="25%">
                    <div class="stat-card">
                        <div class="stat-label">{{ __('reporting::reporting.total_sold_products') }}</div>
                        <div class="stat-value text-info">{{ $totals['total_products'] }}</div>
                        <div class="stat-desc">{{ $totals['total_products_count'] }}</div>
                    </div>
                </td>
                <td width="25%">
                    <div class="stat-card">
                        <div class="stat-label">{{ __('reporting::reporting.highest_sales') }}</div>
                        <div class="stat-value text-warning">{{ Str::limit($totals['highest_sales'], 15) }}</div>
                        <div class="stat-desc">{{ $totals['highest_sales_amount'] }}</div>
                    </div>
                </td>
                <td width="25%">
                    <div class="stat-card">
                        <div class="stat-label">{{ __('reporting::reporting.highest_services') }}</div>
                        <div class="stat-value text-primary">{{ Str::limit($totals['most_booked'], 15) }}</div>
                        <div class="stat-desc">{{ $totals['most_booked_count'] }}</div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- New Patients Section --}}
        <div class="patient-section">
            <div class="section-title">
                {{ __('reporting::reporting.new_patients') }}
                <span class="section-title-badge success">{{ $totals['new_patients_count'] }} {{ __('reporting::reporting.patients') }}</span>
            </div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 30%;">{{ __('reporting::reporting.patient') }}</th>
                        <th style="width: 30%;">{{ __('reporting::reporting.service') }}</th>
                        <th style="width: 25%;">{{ __('reporting::reporting.practitioner') }}</th>
                        <th class="text-right" style="width: 15%;">{{ __('reporting::reporting.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($newPatients as $categoryName => $category)
                        <tr class="category-row">
                            <td colspan="3">{{ $categoryName }}</td>
                            <td class="text-right" style="color: #166534;">{{ $category['total_formatted'] }}</td>
                        </tr>
                        @foreach($category['patients'] as $patient)
                            <tr>
                                <td>{{ $patient['patient_name'] }}</td>
                                <td style="color: #666; font-size: 11px;">{{ $patient['service_name'] }}</td>
                                <td style="color: #666; font-size: 11px;">{{ $patient['practitioner_name'] }}</td>
                                <td class="text-right" style="color: #166534; font-weight: 500;">{{ $patient['price_formatted'] }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">{{ __('reporting::reporting.no_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Returning Patients Section --}}
        <div class="patient-section">
            <div class="section-title">
                {{ __('reporting::reporting.returning_patients') }}
                <span class="section-title-badge info">{{ $totals['returning_patients_count'] }} {{ __('reporting::reporting.patients') }}</span>
            </div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 30%;">{{ __('reporting::reporting.patient') }}</th>
                        <th style="width: 30%;">{{ __('reporting::reporting.service') }}</th>
                        <th style="width: 25%;">{{ __('reporting::reporting.practitioner') }}</th>
                        <th class="text-right" style="width: 15%;">{{ __('reporting::reporting.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returningPatients as $categoryName => $category)
                        <tr class="category-row">
                            <td colspan="3">{{ $categoryName }}</td>
                            <td class="text-right" style="color: #1e40af;">{{ $category['total_formatted'] }}</td>
                        </tr>
                        @foreach($category['patients'] as $patient)
                            <tr>
                                <td>{{ $patient['patient_name'] }}</td>
                                <td style="color: #666; font-size: 11px;">{{ $patient['service_name'] }}</td>
                                <td style="color: #666; font-size: 11px;">{{ $patient['practitioner_name'] }}</td>
                                <td class="text-right" style="color: #1e40af; font-weight: 500;">{{ $patient['price_formatted'] }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">{{ __('reporting::reporting.no_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Follow-up Patients Section --}}
        <div class="patient-section">
            <div class="section-title">
                {{ __('reporting::reporting.followup_patients') }}
                <span class="section-title-badge warning">{{ $totals['followup_patients_count'] }} {{ __('reporting::reporting.patients') }}</span>
            </div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 30%;">{{ __('reporting::reporting.patient') }}</th>
                        <th style="width: 30%;">{{ __('reporting::reporting.service') }}</th>
                        <th style="width: 25%;">{{ __('reporting::reporting.practitioner') }}</th>
                        <th class="text-right" style="width: 15%;">{{ __('reporting::reporting.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($followUpPatients as $categoryName => $category)
                        <tr class="category-row">
                            <td colspan="3">{{ $categoryName }}</td>
                            <td class="text-right" style="color: #92400e;">{{ $category['total_formatted'] }}</td>
                        </tr>
                        @foreach($category['patients'] as $patient)
                            <tr>
                                <td>{{ $patient['patient_name'] }}</td>
                                <td style="color: #666; font-size: 11px;">{{ $patient['service_name'] }}</td>
                                <td style="color: #666; font-size: 11px;">{{ $patient['practitioner_name'] }}</td>
                                <td class="text-right" style="color: #92400e; font-weight: 500;">{{ $patient['price_formatted'] }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">{{ __('reporting::reporting.no_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>{{ __('reporting::reporting.generated_at') }}: {{ $generatedAt->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
