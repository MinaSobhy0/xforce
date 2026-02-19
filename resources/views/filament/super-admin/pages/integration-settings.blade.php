<x-filament-panels::page>
    <div class="space-y-6">
        {{-- WhatsApp Integration --}}
        <x-filament::section
            icon="heroicon-o-chat-bubble-left-right"
            icon-color="success"
            collapsible
        >
            <x-slot name="heading">
                WhatsApp Business API
            </x-slot>

            <x-slot name="description">
                Configure WhatsApp Business API for appointment reminders and patient communication.
            </x-slot>

            <form wire:submit="saveWhatsapp">
                {{ $this->whatsappForm }}

                <div class="mt-4 flex items-center gap-3">
                    <x-filament::button type="submit">
                        Save WhatsApp Settings
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="testWhatsapp"
                    >
                        Test Connection
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- SMS Integration --}}
        <x-filament::section
            icon="heroicon-o-device-phone-mobile"
            icon-color="info"
            collapsible
        >
            <x-slot name="heading">
                SMS Gateway
            </x-slot>

            <x-slot name="description">
                Configure SMS provider for appointment reminders and OTP verification.
            </x-slot>

            <form wire:submit="saveSms">
                {{ $this->smsForm }}

                <div class="mt-4 flex items-center gap-3">
                    <x-filament::button type="submit">
                        Save SMS Settings
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="testSms"
                    >
                        Test Connection
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Cloud Storage --}}
        <x-filament::section
            icon="heroicon-o-cloud-arrow-up"
            icon-color="warning"
            collapsible
        >
            <x-slot name="heading">
                Cloud Storage (S3)
            </x-slot>

            <x-slot name="description">
                Configure cloud storage for tenant file uploads. Recommended for production with multiple tenants.
            </x-slot>

            <form wire:submit="saveStorage">
                {{ $this->storageForm }}

                <div class="mt-4 flex items-center gap-3">
                    <x-filament::button type="submit">
                        Save Storage Settings
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="testStorage"
                    >
                        Test Connection
                    </x-filament::button>
                </div>
            </form>

            <x-slot name="footerActions">
                <x-filament::link
                    href="https://docs.aws.amazon.com/AmazonS3/latest/userguide/creating-bucket.html"
                    target="_blank"
                    icon="heroicon-o-arrow-top-right-on-square"
                >
                    AWS S3 Setup Guide
                </x-filament::link>
            </x-slot>
        </x-filament::section>

        {{-- Payment Gateway --}}
        <x-filament::section
            icon="heroicon-o-credit-card"
            icon-color="primary"
            collapsible
        >
            <x-slot name="heading">
                Payment Gateway
            </x-slot>

            <x-slot name="description">
                Configure payment processing for subscription billing.
            </x-slot>

            <form wire:submit="savePayment">
                {{ $this->paymentForm }}

                <div class="mt-4">
                    <x-filament::button type="submit">
                        Save Payment Settings
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Integration Status --}}
        <x-filament::section>
            <x-slot name="heading">
                Integration Status
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl mb-1">
                        @if($whatsappData['whatsapp_enabled'] ?? false)
                            <span class="text-green-500">&#10003;</span>
                        @else
                            <span class="text-gray-400">&#x2212;</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">WhatsApp</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl mb-1">
                        @if($smsData['sms_enabled'] ?? false)
                            <span class="text-green-500">&#10003;</span>
                        @else
                            <span class="text-gray-400">&#x2212;</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">SMS</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl mb-1">
                        @if(($storageData['storage_driver'] ?? 'local') !== 'local')
                            <span class="text-green-500">&#10003;</span>
                        @else
                            <span class="text-yellow-500">!</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Cloud Storage</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                    <div class="text-2xl mb-1">
                        @if(!empty($paymentData['payment_gateway']))
                            <span class="text-green-500">&#10003;</span>
                        @else
                            <span class="text-gray-400">&#x2212;</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Payments</div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
