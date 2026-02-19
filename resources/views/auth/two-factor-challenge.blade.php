<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Two-Factor Authentication') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ __('Two-Factor Authentication') }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Please enter your authentication code to continue.') }}
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-800 rounded-lg">
                    <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div x-data="{ recovery: false }">
                {{-- Authenticator Code Form --}}
                <form method="POST" action="{{ route('two-factor.challenge') }}" x-show="!recovery" class="space-y-6">
                    @csrf

                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Authentication Code') }}
                        </label>
                        <input
                            id="code"
                            type="text"
                            name="code"
                            inputmode="numeric"
                            autofocus
                            autocomplete="one-time-code"
                            maxlength="6"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-center text-2xl tracking-widest"
                            placeholder="000000"
                        >
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Enter the 6-digit code from your authenticator app.') }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            @click="recovery = true"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline"
                        >
                            {{ __('Use a recovery code') }}
                        </button>

                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        >
                            {{ __('Verify') }}
                        </button>
                    </div>
                </form>

                {{-- Recovery Code Form --}}
                <form method="POST" action="{{ route('two-factor.challenge') }}" x-show="recovery" x-cloak class="space-y-6">
                    @csrf

                    <div>
                        <label for="recovery_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Recovery Code') }}
                        </label>
                        <input
                            id="recovery_code"
                            type="text"
                            name="recovery_code"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 font-mono"
                            placeholder="XXXXXXXXXX"
                        >
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Enter one of your emergency recovery codes.') }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            @click="recovery = false"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline"
                        >
                            {{ __('Use an authentication code') }}
                        </button>

                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        >
                            {{ __('Verify') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a
                    href="{{ route('filament.super-admin.auth.login') }}"
                    class="flex items-center justify-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('Back to login') }}
                </a>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
