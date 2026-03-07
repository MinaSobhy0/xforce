<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: 600;
            color: {{ $textColor }};
            margin-bottom: 15px;
        }
        .message {
            font-size: 16px;
            color: #666;
            line-height: 1.6;
            white-space: pre-line;
        }
        .status-badge {
            display: inline-block;
            background: {{ $bgColor }};
            color: {{ $textColor }};
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{{ $icon }}</div>
        <h1 class="title">{{ $title }}</h1>
        @if($message)
            <p class="message">{{ $message }}</p>
        @endif
    </div>
</body>
</html>
