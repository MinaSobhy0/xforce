<x-filament-panels::page>
    <div class="space-y-6">
        {{-- reCAPTCHA v3 --}}
        <x-filament::section
            icon="heroicon-o-shield-check"
            icon-color="success"
        >
            <x-slot name="heading">
                reCAPTCHA v3
            </x-slot>

            <x-slot name="description">
                Invisible spam protection for contact form and other public forms. reCAPTCHA v3 runs in the background without user interaction.
            </x-slot>

            <form wire:submit="saveRecaptcha">
                {{ $this->recaptchaForm }}

                <div class="mt-4">
                    <x-filament::button type="submit">
                        Save reCAPTCHA Settings
                    </x-filament::button>
                </div>
            </form>

            <x-slot name="footerActions">
                <x-filament::link
                    href="https://developers.google.com/recaptcha/docs/v3"
                    target="_blank"
                    icon="heroicon-o-arrow-top-right-on-square"
                >
                    reCAPTCHA v3 Documentation
                </x-filament::link>
            </x-slot>
        </x-filament::section>

        {{-- Security Status --}}
        <x-filament::section>
            <x-slot name="heading">
                Security Status
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl mb-1">
                        @if($recaptchaData['recaptcha_enabled'] ?? false)
                            <span class="text-green-500">&#10003;</span>
                        @else
                            <span class="text-gray-400">&#x2212;</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">reCAPTCHA v3</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl mb-1">
                        <span class="text-green-500">&#10003;</span>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">HTTPS</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl mb-1">
                        <span class="text-green-500">&#10003;</span>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">CSRF Protection</div>
                </div>
            </div>
        </x-filament::section>

        {{-- How it works --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                How reCAPTCHA v3 Works
            </x-slot>

            <div class="prose dark:prose-invert max-w-none text-sm">
                <ul>
                    <li><strong>Invisible:</strong> No checkbox or challenge for users</li>
                    <li><strong>Score-based:</strong> Returns a score from 0.0 (bot) to 1.0 (human)</li>
                    <li><strong>Minimum Score:</strong> Forms are rejected if score is below your threshold</li>
                    <li><strong>Actions:</strong> Each form action is tracked separately for analytics</li>
                </ul>
                <p class="mt-4">
                    <strong>Recommended minimum score:</strong> 0.5 for most forms. Increase to 0.7 for sensitive actions.
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
