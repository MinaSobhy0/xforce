@php
    $patient = $patient ?? null;
    $balance = $patient?->balance_minor ?? 0;
@endphp

<div class="flex items-center gap-2">
    @if($balance > 0)
        <a href="{{ route('filament.tenant.resources.payments.create', ['patient_id' => $patient->id]) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-success-600 hover:bg-success-500 rounded-lg transition-colors">
            <x-heroicon-o-banknotes class="w-4 h-4" />
            {{ __('patients::patients.balance.pay_balance') }}
        </a>
    @endif

    <a href="{{ route('filament.tenant.pages.partner-ledger-page', ['partner_type' => 'customer', 'partner_id' => $patient->id]) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
        <x-heroicon-o-document-text class="w-4 h-4" />
        {{ __('patients::patients.balance.view_ledger') }}
    </a>
</div>
