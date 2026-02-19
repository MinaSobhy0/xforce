<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .content {
            padding: 30px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #2563EB;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0;">Revenue Analytics Report</h1>
        <p style="margin: 10px 0 0; opacity: 0.9;">
            {{ $period['start'] ?? 'All Time' }} - {{ $period['end'] ?? 'Present' }}
        </p>
    </div>

    <div class="content">
        <p>Hello {{ $user->first_name ?? 'Admin' }},</p>

        <p>Your requested Revenue Analytics Report is attached to this email as a PDF document.</p>

        <p>This report includes:</p>
        <ul>
            <li>Revenue summary metrics (MRR, ARR, Total Revenue)</li>
            <li>Invoice details for the selected period</li>
            <li>Payment status breakdown</li>
        </ul>

        <p>
            <a href="{{ url('/super-admin/revenue-analytics') }}" class="button">
                View Live Dashboard
            </a>
        </p>

        <p style="margin-top: 30px; color: #64748b; font-size: 14px;">
            If you have any questions about this report, please contact the platform administrator.
        </p>
    </div>

    <div class="footer">
        <p>
            This is an automated email from XLinic Platform.<br>
            &copy; {{ date('Y') }} XLinic. All rights reserved.
        </p>
    </div>
</body>
</html>
