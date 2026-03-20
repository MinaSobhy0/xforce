<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join {{ $tenant->name }} - X-Linic</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8 text-center">
        @if($tenant->logo_path)
            <img src="{{ $tenant->getLogoUrl() }}" alt="{{ $tenant->name }}" class="h-20 mx-auto mb-6">
        @else
            <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <span class="text-3xl font-bold text-primary-600">{{ substr($tenant->name, 0, 1) }}</span>
            </div>
        @endif

        <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $tenant->name }}</h1>
        <p class="text-gray-500 mb-8">Staff Mobile App</p>

        <div class="space-y-4">
            <a href="{{ $deepLink }}"
               class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                Open in App
            </a>

            <div class="text-sm text-gray-400">
                Don't have the app?
            </div>

            <div class="flex gap-3">
                <a href="#" class="flex-1 border border-gray-300 rounded-lg py-3 px-4 hover:bg-gray-50 transition">
                    <svg class="w-6 h-6 mx-auto" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.53 4.08zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                    </svg>
                    <span class="text-xs mt-1 block">App Store</span>
                </a>
                <a href="#" class="flex-1 border border-gray-300 rounded-lg py-3 px-4 hover:bg-gray-50 transition">
                    <svg class="w-6 h-6 mx-auto" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-3.198l2.807 1.626a1 1 0 010 1.73l-2.808 1.626L15.206 12l2.492-2.491zM5.864 2.658L16.8 8.99l-2.302 2.302-8.634-8.634z"/>
                    </svg>
                    <span class="text-xs mt-1 block">Google Play</span>
                </a>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-100">
            <p class="text-xs text-gray-400">
                Code: <span class="font-mono">{{ $code }}</span>
            </p>
        </div>
    </div>
</body>
</html>
