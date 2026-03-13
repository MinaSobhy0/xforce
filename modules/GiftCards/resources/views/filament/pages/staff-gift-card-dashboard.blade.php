<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Cards by Denomination --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('giftcards::giftcards.staff_dashboard.cards_by_denomination') }}
            </x-slot>

            @if($this->cardsByDenomination->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">
                    {{ __('giftcards::giftcards.staff_dashboard.no_cards_assigned') }}
                </p>
            @else
                <div class="flex flex-wrap gap-3">
                    @foreach($this->cardsByDenomination as $item)
                        <div class="flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <span class="font-medium">{{ $item['value'] }}</span>
                            <span class="px-2 py-0.5 bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 rounded-full text-sm font-medium">
                                {{ $item['count'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Available Cards Table --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('giftcards::giftcards.staff_dashboard.available_cards') }}
            </x-slot>

            @if($this->availableCards->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                    {{ __('giftcards::giftcards.staff_dashboard.no_cards_assigned') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-gray-700">
                                <th class="text-start p-3">{{ __('giftcards::giftcards.fields.code') }}</th>
                                <th class="text-start p-3">{{ __('giftcards::giftcards.fields.template') }}</th>
                                <th class="text-start p-3">{{ __('giftcards::giftcards.fields.value') }}</th>
                                <th class="text-start p-3">{{ __('giftcards::giftcards.fields.expires_at') }}</th>
                                <th class="text-end p-3">{{ __('giftcards::giftcards.actions.activate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->availableCards as $card)
                                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="p-3 font-mono">{{ $card->code }}</td>
                                    <td class="p-3">{{ $card->template?->name ?? '-' }}</td>
                                    <td class="p-3 font-medium text-success-600 dark:text-success-400">
                                        {{ $card->formatted_initial_value }}
                                    </td>
                                    <td class="p-3">
                                        @if($card->expires_at)
                                            {{ $card->expires_at->format('d/m/Y') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="p-3 text-end">
                                        <x-filament::button
                                            size="sm"
                                            color="success"
                                            wire:click="openSellModal('{{ $card->id }}')"
                                        >
                                            {{ __('giftcards::giftcards.staff_dashboard.sell') }}
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Sell Card Modal --}}
    <x-filament::modal id="sell-card-modal" width="lg">
        <x-slot name="heading">
            {{ __('giftcards::giftcards.staff_dashboard.sell_card') }}
        </x-slot>

        <form wire:submit="sellCard">
            {{ $this->sellForm }}

            <div class="flex justify-end gap-3 mt-6">
                <x-filament::button
                    type="button"
                    color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'sell-card-modal' })"
                >
                    {{ __('giftcards::giftcards.close') }}
                </x-filament::button>
                <x-filament::button type="submit" color="success">
                    {{ __('giftcards::giftcards.staff_dashboard.complete_sale') }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::modal>
</x-filament-panels::page>
