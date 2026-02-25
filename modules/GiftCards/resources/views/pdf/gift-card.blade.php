<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Card - {{ $card->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            background: #f5f5f5;
            padding: 10px;
        }

        .card {
            width: 400px;
            height: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }

        .clinic-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .gift-card-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .value {
            font-size: 36px;
            font-weight: bold;
            margin: 30px 0;
            text-align: center;
        }

        .card-code {
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 3px;
            text-align: center;
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .pin-section {
            text-align: center;
            margin-top: 10px;
        }

        .pin-label {
            font-size: 10px;
            opacity: 0.8;
        }

        .pin-code {
            font-family: monospace;
            font-size: 14px;
            letter-spacing: 2px;
        }

        .footer {
            position: absolute;
            bottom: 15px;
            left: 20px;
            right: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            opacity: 0.7;
        }

        .expiry {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="clinic-name">{{ $clinic['name'] ?? config('app.name') }}</div>
        <div class="gift-card-label">Gift Card</div>

        <div class="value">{{ $card->formatted_initial_value }}</div>

        <div class="card-code">{{ $card->code }}</div>

        @if($card->pin_code)
        <div class="pin-section">
            <div class="pin-label">PIN</div>
            <div class="pin-code">{{ $card->pin_code }}</div>
        </div>
        @endif

        <div class="footer">
            <div>
                @if($card->template)
                {{ $card->template->name }}
                @endif
            </div>
            <div class="expiry">
                @if($card->expires_at)
                Valid until: {{ $card->expires_at->format('d/m/Y') }}
                @else
                No Expiry
                @endif
            </div>
        </div>
    </div>
</body>
</html>
