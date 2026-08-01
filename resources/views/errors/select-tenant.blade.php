<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Clinic - XLinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Select Clinic</h1>
            <p class="text-gray-600 text-sm">
                You're accessing the system via IP address. Please select which clinic you want to access.
            </p>
        </div>

        <div class="space-y-3">
            @foreach($tenants as $tenant)
                <a href="{{ $currentUrl }}{{ str_contains($currentUrl, '?') ? '&' : '?' }}_tenant={{ $tenant->slug }}"
                   class="flex items-center gap-4 p-4 rounded-lg border border-gray-200 hover:border-indigo-500 hover:bg-indigo-50 transition-all group">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600 truncate">
                            {{ $tenant->name }}
                        </h3>
                        <p class="text-sm text-gray-500 truncate">{{ $tenant->slug }}.xforcehr.com</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            @endforeach
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <p class="text-xs text-gray-500 text-center">
                For production use, access clinics via their subdomain:
                <code class="bg-gray-100 px-1 rounded">clinic.xforcehr.com/admin</code>
            </p>
        </div>
    </div>
</body>
</html>
