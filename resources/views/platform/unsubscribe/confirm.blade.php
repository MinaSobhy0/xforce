<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribe — XLinic</title>
    <style>
        body { margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:#f6f7f9; color:#111827; }
        .card { max-width:480px; margin:80px auto; background:#fff; border-radius:12px; padding:36px; box-shadow:0 4px 12px rgba(0,0,0,.06); }
        h1 { font-size:22px; margin:0 0 12px; }
        p { line-height:1.6; color:#4b5563; margin:0 0 20px; }
        form { margin:0; }
        button, .btn { display:inline-block; padding:12px 24px; border-radius:8px; font-size:15px; font-weight:500; cursor:pointer; border:0; text-decoration:none; }
        .btn-primary { background:#dc2626; color:#fff; }
        .btn-primary:hover { background:#b91c1c; }
        .btn-secondary { background:#f3f4f6; color:#374151; margin-left:8px; }
        .email { background:#f3f4f6; padding:2px 8px; border-radius:4px; font-family:ui-monospace, monospace; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Unsubscribe from XLinic emails?</h1>
        <p>You're about to unsubscribe <span class="email">{{ $email }}</span> from all XLinic platform marketing emails. Transactional emails (billing, security, contract signing) will continue.</p>
        <form method="POST" action="{{ $confirmUrl }}">
            @csrf
            <button type="submit" class="btn-primary">Yes, unsubscribe</button>
            <a href="{{ config('app.url') }}" class="btn-secondary">Nevermind</a>
        </form>
    </div>
</body>
</html>
