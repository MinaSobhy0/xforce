<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed — XLinic</title>
    <style>
        body { margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:#f6f7f9; color:#111827; }
        .card { max-width:480px; margin:80px auto; background:#fff; border-radius:12px; padding:36px; box-shadow:0 4px 12px rgba(0,0,0,.06); text-align:center; }
        .check { width:56px; height:56px; margin:0 auto 20px; background:#dcfce7; color:#166534; border-radius:50%; line-height:56px; font-size:28px; font-weight:700; }
        h1 { font-size:22px; margin:0 0 12px; }
        p { line-height:1.6; color:#4b5563; margin:0 0 20px; }
        a { color:#4f46e5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="check">✓</div>
        <h1>You're unsubscribed.</h1>
        <p><strong>{{ $email }}</strong> will no longer receive XLinic platform marketing emails.</p>
        <p><a href="{{ config('app.url') }}">Return to xlinic.com</a></p>
    </div>
</body>
</html>
