<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('errors.tenant_suspended.title') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f7f8fa;
            --card: #ffffff;
            --border: rgba(15, 23, 42, 0.08);
            --text: #0f172a;
            --muted: #64748b;
            --accent: #4f46e5;
            --warning: #f59e0b;
        }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', 'IBM Plex Sans Arabic', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 2.5rem 2.5rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 24px 48px -28px rgba(15, 23, 42, 0.18);
            max-width: 28rem;
            width: 100%;
            text-align: center;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(245, 158, 11, 0.12);
            color: var(--warning);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            margin: 0 0 0.75rem 0;
        }
        p {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0 0 1.25rem 0;
        }
        p.clinic {
            color: var(--text);
            font-weight: 500;
            margin-bottom: 1.75rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        a.btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            border-radius: 0.6rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }
        a.btn-primary {
            background: var(--accent);
            color: white;
        }
        a.btn-primary:hover { background: #4338ca; }
        a.btn-secondary {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
        }
        a.btn-secondary:hover { color: var(--text); background: rgba(15, 23, 42, 0.03); }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0a0a0f;
                --card: #14141c;
                --border: rgba(255, 255, 255, 0.08);
                --text: #f1f5f9;
                --muted: #94a3b8;
            }
            .badge { background: rgba(245, 158, 11, 0.16); }
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">{{ __('errors.tenant_suspended.label') }}</span>

        <h1>{{ __('errors.tenant_suspended.title') }}</h1>

        @isset($tenantName)
            <p class="clinic">{{ $tenantName }}</p>
        @endisset

        <p>{{ __('errors.tenant_suspended.body') }}</p>

        @isset($contactEmail)
            <p>{!! __('errors.tenant_suspended.contact', ['email' => '<a href="mailto:' . e($contactEmail) . '">' . e($contactEmail) . '</a>']) !!}</p>
        @endisset

        <div class="actions">
            <a class="btn btn-primary" href="{{ $platformAdminUrl ?? 'https://xforcehr.com/admin' }}">{{ __('errors.tenant_suspended.go_platform') }}</a>
        </div>
    </div>
</body>
</html>
