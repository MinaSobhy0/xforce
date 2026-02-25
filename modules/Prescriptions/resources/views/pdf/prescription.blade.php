<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('prescriptions::prescription.pdf.title') }} #{{ $meta['prescription_number'] }}</title>
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
            max-width: 100%;
            margin: 0 auto;
            padding: 15px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 12px;
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
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 3px;
        }
        .clinic-info {
            color: #666;
            font-size: 10px;
        }
        .rx-symbol {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            font-style: italic;
        }
        .prescription-meta {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        .prescription-meta strong {
            color: #333;
        }
        .patient-section {
            background-color: #f3f4f6;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 12px;
        }
        .patient-row {
            display: table;
            width: 100%;
        }
        .patient-cell {
            display: table-cell;
            width: 25%;
            padding: 3px 5px;
        }
        .patient-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        .patient-value {
            font-size: 11px;
            font-weight: bold;
        }
        .diagnosis-section {
            background-color: #fef3c7;
            padding: 8px 10px;
            border-radius: 5px;
            margin-bottom: 12px;
            border-left: 3px solid #f59e0b;
        }
        .diagnosis-label {
            font-size: 9px;
            color: #92400e;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .diagnosis-text {
            font-size: 11px;
            color: #333;
        }
        .medications-section {
            margin-bottom: 15px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #2563eb;
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 1px solid #dbeafe;
        }
        .medication-item {
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            margin-bottom: 8px;
            background-color: #fff;
        }
        .medication-header {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .medication-number {
            display: table-cell;
            width: 25px;
            vertical-align: top;
        }
        .medication-number-badge {
            background-color: #2563eb;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            font-size: 10px;
            font-weight: bold;
        }
        .medication-details {
            display: table-cell;
            vertical-align: top;
        }
        .medication-name {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
        }
        .medication-generic {
            font-size: 10px;
            color: #6b7280;
            font-style: italic;
        }
        .medication-form {
            font-size: 10px;
            color: #2563eb;
            background-color: #dbeafe;
            padding: 1px 6px;
            border-radius: 10px;
            display: inline-block;
            margin-{{ $isRtl ? 'right' : 'left' }}: 5px;
        }
        .medication-info {
            display: table;
            width: 100%;
            margin-top: 5px;
        }
        .medication-info-cell {
            display: table-cell;
            width: 25%;
            padding: 2px 5px;
        }
        .info-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .info-value {
            font-size: 10px;
            font-weight: 600;
        }
        .special-instructions {
            margin-top: 5px;
            padding: 5px;
            background-color: #fef3c7;
            border-radius: 3px;
            font-size: 10px;
            color: #92400e;
        }
        .notes-section {
            background-color: #f3f4f6;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .notes-title {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .notes-text {
            font-size: 10px;
        }
        .validity-section {
            text-align: center;
            padding: 8px;
            background-color: {{ $meta['is_expired'] ? '#fee2e2' : '#dcfce7' }};
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .validity-text {
            font-size: 10px;
            color: {{ $meta['is_expired'] ? '#991b1b' : '#166534' }};
            font-weight: bold;
        }
        .signature-section {
            display: table;
            width: 100%;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #d1d5db;
        }
        .signature-left, .signature-right {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
        }
        .signature-right {
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }
        .prescriber-name {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
        }
        .prescriber-info {
            font-size: 10px;
            color: #6b7280;
        }
        .signature-line {
            width: 150px;
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 5px;
            font-size: 9px;
            color: #666;
            margin-{{ $isRtl ? 'right' : 'left' }}: auto;
        }
        .footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 8px;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: rgba(220, 38, 38, 0.1);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }
        @if($meta['status'] === 'cancelled')
        .watermark-cancelled {
            display: block;
        }
        @endif
    </style>
</head>
<body>
    @if($meta['status'] === 'cancelled')
    <div class="watermark">{{ __('prescriptions::prescription.pdf.cancelled') }}</div>
    @endif

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="clinic-name">{{ $clinic['name'] }}</div>
                <div class="clinic-info">
                    @if($clinic['address'])<div>{{ $clinic['address'] }}</div>@endif
                    @if($clinic['phone'])<div>{{ __('prescriptions::prescription.pdf.phone') }}: {{ $clinic['phone'] }}</div>@endif
                    @if($clinic['license'])<div>{{ __('prescriptions::prescription.pdf.license') }}: {{ $clinic['license'] }}</div>@endif
                </div>
            </div>
            <div class="header-right">
                <div class="rx-symbol">Rx</div>
                <div class="prescription-meta">
                    <div><strong>#{{ $meta['prescription_number'] }}</strong></div>
                    <div>{{ __('prescriptions::prescription.pdf.date') }}: {{ $meta['issued_date'] }}</div>
                </div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="patient-section">
            <div class="patient-row">
                <div class="patient-cell" style="width: 40%;">
                    <div class="patient-label">{{ __('prescriptions::prescription.pdf.patient_name') }}</div>
                    <div class="patient-value">{{ $patient['name'] }}</div>
                </div>
                <div class="patient-cell" style="width: 20%;">
                    <div class="patient-label">{{ __('prescriptions::prescription.pdf.age') }}</div>
                    <div class="patient-value">{{ $patient['age'] }} {{ __('prescriptions::prescription.years') }}</div>
                </div>
                <div class="patient-cell" style="width: 15%;">
                    <div class="patient-label">{{ __('prescriptions::prescription.pdf.gender') }}</div>
                    <div class="patient-value">{{ $patient['gender'] }}</div>
                </div>
                <div class="patient-cell" style="width: 25%;">
                    <div class="patient-label">{{ __('prescriptions::prescription.pdf.patient_code') }}</div>
                    <div class="patient-value">{{ $patient['code'] }}</div>
                </div>
            </div>
        </div>

        <!-- Diagnosis -->
        @if($meta['diagnosis'])
        <div class="diagnosis-section">
            <div class="diagnosis-label">{{ __('prescriptions::prescription.pdf.diagnosis') }}</div>
            <div class="diagnosis-text">{{ $meta['diagnosis'] }}</div>
        </div>
        @endif

        <!-- Medications -->
        <div class="medications-section">
            <div class="section-title">{{ __('prescriptions::prescription.pdf.medications') }}</div>

            @foreach($medications as $medication)
            <div class="medication-item">
                <div class="medication-header">
                    <div class="medication-number">
                        <div class="medication-number-badge">{{ $medication['number'] }}</div>
                    </div>
                    <div class="medication-details">
                        <span class="medication-name">{{ $medication['medication_name'] }}</span>
                        @if($medication['form'])
                        <span class="medication-form">{{ $medication['form'] }}</span>
                        @endif
                        @if($medication['generic_name'])
                        <div class="medication-generic">({{ $medication['generic_name'] }})</div>
                        @endif
                    </div>
                </div>

                <div class="medication-info">
                    @if($medication['dosage'])
                    <div class="medication-info-cell">
                        <div class="info-label">{{ __('prescriptions::prescription.pdf.dosage') }}</div>
                        <div class="info-value">{{ $medication['dosage'] }}</div>
                    </div>
                    @endif
                    @if($medication['frequency'])
                    <div class="medication-info-cell">
                        <div class="info-label">{{ __('prescriptions::prescription.pdf.frequency') }}</div>
                        <div class="info-value">{{ $medication['frequency'] }}</div>
                    </div>
                    @endif
                    @if($medication['duration'])
                    <div class="medication-info-cell">
                        <div class="info-label">{{ __('prescriptions::prescription.pdf.duration') }}</div>
                        <div class="info-value">{{ $medication['duration'] }}</div>
                    </div>
                    @endif
                    @if($medication['quantity'])
                    <div class="medication-info-cell">
                        <div class="info-label">{{ __('prescriptions::prescription.pdf.quantity') }}</div>
                        <div class="info-value">{{ $medication['quantity'] }}</div>
                    </div>
                    @endif
                </div>

                @if($medication['route'] || $medication['instructions'])
                <div class="medication-info">
                    @if($medication['route'])
                    <div class="medication-info-cell">
                        <div class="info-label">{{ __('prescriptions::prescription.pdf.route') }}</div>
                        <div class="info-value">{{ $medication['route'] }}</div>
                    </div>
                    @endif
                    @if($medication['instructions'])
                    <div class="medication-info-cell" style="width: 50%;">
                        <div class="info-label">{{ __('prescriptions::prescription.pdf.instructions') }}</div>
                        <div class="info-value">{{ $medication['instructions'] }}</div>
                    </div>
                    @endif
                    @if($medication['refills'] > 0)
                    <div class="medication-info-cell">
                        <div class="info-label">{{ __('prescriptions::prescription.pdf.refills') }}</div>
                        <div class="info-value">{{ $medication['refills'] }}</div>
                    </div>
                    @endif
                </div>
                @endif

                @if($medication['special_instructions'])
                <div class="special-instructions">
                    <strong>{{ __('prescriptions::prescription.pdf.special_note') }}:</strong> {{ $medication['special_instructions'] }}
                </div>
                @endif
            </div>
            @endforeach
        </div>

        <!-- Notes -->
        @if($meta['notes'])
        <div class="notes-section">
            <div class="notes-title">{{ __('prescriptions::prescription.pdf.additional_notes') }}</div>
            <div class="notes-text">{{ $meta['notes'] }}</div>
        </div>
        @endif

        <!-- Validity -->
        <div class="validity-section">
            <div class="validity-text">
                @if($meta['is_expired'])
                    {{ __('prescriptions::prescription.pdf.expired') }}
                @else
                    {{ __('prescriptions::prescription.pdf.valid_until') }}: {{ $meta['valid_until'] }}
                @endif
            </div>
        </div>

        <!-- Signature -->
        <div class="signature-section">
            <div class="signature-left">
                <div class="prescriber-name">{{ $prescriber['title'] }} {{ $prescriber['name'] }}</div>
                <div class="prescriber-info">
                    @if($prescriber['specialty'])<div>{{ $prescriber['specialty'] }}</div>@endif
                    @if($prescriber['license'])<div>{{ __('prescriptions::prescription.pdf.license') }}: {{ $prescriber['license'] }}</div>@endif
                </div>
            </div>
            <div class="signature-right">
                <div class="signature-line">
                    {{ __('prescriptions::prescription.pdf.signature') }}
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>{{ __('prescriptions::prescription.pdf.footer_text') }}</p>
        </div>
    </div>
</body>
</html>
